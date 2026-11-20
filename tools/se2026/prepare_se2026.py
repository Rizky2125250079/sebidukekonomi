#!/usr/bin/env python3
"""
Menyiapkan data "Sebaran Usaha SE2026" untuk diimpor ke database Laravel.

Masukan  : Excel hasil SE2026, GeoJSON titik geotagging, GeoJSON batas SLS.
Keluaran : storage/app/se2026/{wilayah,usaha,titik}.csv  (dibaca `php artisan se2026:import`)
           public/wilayah/sls_1674_2026-1.min.geojson     (peta ringan, 1 poligon per RT/SLS)

Pakai   : pip install pandas openpyxl shapely
          python tools/se2026/prepare_se2026.py \
              --excel  USAHA_CLEAN_KOORDINAT.xlsx \
              --titik  1674_geotagging_lat_long_only.geojson \
              --sls    final_sls_1674_2026-1.geojson

Catatan penting
* Koordinat titik diambil dari GEOMETRI GeoJSON. Properti `latitude_edited` /
  `longitude_edited` pada file itu BUKAN koordinat (isinya ID subSLS dan 0/1), jadi diabaikan.
* Wilayah (kecamatan/kelurahan/RT) tiap titik ditentukan lewat poligon SLS (spatial join),
  sehingga titik selalu tampil di dalam poligon yang sedang dipilih.
* Data pribadi (NIK, nama penanggung jawab, dsb.) sengaja TIDAK dibawa ke web.
"""
import argparse, csv, json, re, sys
from pathlib import Path

import pandas as pd
from shapely import STRtree, points as make_points
from shapely.geometry import mapping, shape
from shapely.ops import unary_union

ROOT = Path(__file__).resolve().parents[2]

C_USAHA = "Identitas_Nama_dan_Alamat_Usaha | Usaha"
C_KEG = "Kegiatan_Utama_KBLI_Jaringan_R13_R14 | Kegiatan"
SKALA_OK = {"UMKM", "UMK", "UM", "UB"}


def bersih(v):
    """Rapikan teks: buang karakter kontrol & spasi ganda."""
    if v is None or (isinstance(v, float) and pd.isna(v)):
        return ""
    return re.sub(r"\s+", " ", re.sub(r"[\x00-\x1f\x7f]", " ", str(v))).strip()


def bulatkan(coords, n=6):
    if isinstance(coords[0], (int, float)):
        return [round(coords[0], n), round(coords[1], n)]
    return [bulatkan(c, n) for c in coords]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--excel", required=True)
    ap.add_argument("--titik", required=True)
    ap.add_argument("--sls", required=True)
    ap.add_argument("--out-dir", default=str(ROOT / "storage/app/se2026"))
    ap.add_argument("--geo-out", default=str(ROOT / "public/wilayah/sls_1674_2026-1.min.geojson"))
    ap.add_argument("--margin", type=float, default=0.01, help="margin bbox (derajat) untuk membuang titik pencilan")
    a = ap.parse_args()
    out = Path(a.out_dir); out.mkdir(parents=True, exist_ok=True)

    # ---------- 1. batas SLS ----------
    print("[1/4] Membaca batas SLS ...")
    sls = json.load(open(a.sls, encoding="utf-8"))["features"]
    sub_geoms, sub_idsls, rows = [], [], {}
    for f in sls:
        p = f["properties"]
        g = shape(f["geometry"])
        if not g.is_valid:
            g = g.buffer(0)
        sub_geoms.append(g)
        sub_idsls.append(p["idsubsls"])
        rows.setdefault(p["idsls"], dict(
            idsls=p["idsls"], nmsls=bersih(p["nmsls"]), kdkec=p["kdkec"], nmkec=bersih(p["nmkec"]),
            kddesa=p["kddesa"], nmdesa=bersih(p["nmdesa"]), ada_peta=1))
    # idsls di GeoJSON = 14 digit (prov+kab+kec+desa+sls) -> lengkapi kode kecamatan 3 digit
    for r in rows.values():
        r["kdkec"] = r["idsls"][4:7]; r["kddesa"] = r["idsls"][7:10]

    # peta ringan: gabungkan subSLS menjadi satu poligon per RT/SLS
    groups = {}
    for g, sid in zip(sub_geoms, sub_idsls):
        groups.setdefault(sid[:14], []).append(g)
    feats = []
    for idsls, gl in sorted(groups.items()):
        g = unary_union(gl).simplify(0.000005, preserve_topology=True)
        r = rows[idsls]
        feats.append({"type": "Feature",
                      "properties": {k: r[k] for k in ("idsls", "nmsls", "kdkec", "nmkec", "kddesa", "nmdesa")},
                      "geometry": {"type": g.geom_type, "coordinates": bulatkan(mapping(g)["coordinates"])}})
    Path(a.geo_out).parent.mkdir(parents=True, exist_ok=True)
    json.dump({"type": "FeatureCollection", "features": feats}, open(a.geo_out, "w", encoding="utf-8"),
              ensure_ascii=False, separators=(",", ":"))
    print(f"      {len(sls)} subSLS -> {len(feats)} RT/SLS | peta ringan {Path(a.geo_out).stat().st_size/1024:.0f} KB")

    # ---------- 2. usaha (Excel) ----------
    print("[2/4] Membaca Excel (butuh beberapa puluh detik) ...")
    cols = ["ID_SUBSLS", "Kecamatan", "Kelurahan_Desa", "assignment_id", "ID_Roster_Usaha", "KBLI", "Kategori_KBLI",
            "Tahun_Mulai_Komersial_R25", "Skala_Usaha", C_USAHA, C_KEG]
    x = pd.read_excel(a.excel, dtype=str, usecols=cols)
    n0 = len(x)
    x = x[x["ID_SUBSLS"].fillna("").str.fullmatch(r"\d{16}") & x["assignment_id"].notna()].copy()
    if len(x) != n0:
        print(f"      ! {n0-len(x)} baris dibuang (ID_SUBSLS/assignment_id tidak valid)")

    u = pd.DataFrame({
        "assignment_id": x["assignment_id"].str.strip().str.lower(),
        "urut": pd.to_numeric(x["ID_Roster_Usaha"], errors="coerce").fillna(0).astype(int),
        "idsubsls": x["ID_SUBSLS"],
        "idsls": x["ID_SUBSLS"].str[:14],
        "kdkec": x["ID_SUBSLS"].str[4:7],
        "kddesa": x["ID_SUBSLS"].str[7:10],
        "nama": x[C_USAHA].map(bersih),
        "kbli": x["KBLI"].map(bersih).where(lambda s: s.str.fullmatch(r"\d{5}"), "-"),
        "kategori_kbli": x["Kategori_KBLI"].map(bersih).where(lambda s: s.str.fullmatch(r"[A-U]"), ""),
        "tahun_mulai": x["Tahun_Mulai_Komersial_R25"].map(bersih).where(lambda s: s.str.fullmatch(r"(18|19|20)\d{2}"), ""),
        "skala": x["Skala_Usaha"].map(bersih).str.upper().where(lambda s: s.isin(SKALA_OK), "LAINNYA"),
        "kegiatan": x[C_KEG].map(bersih).replace("-", ""),
    })

    # lengkapi wilayah yang ada di Excel tetapi tidak ada di peta (mis. kode SLS 0000)
    nama = x.assign(kdkec=x["ID_SUBSLS"].str[4:7], kddesa=x["ID_SUBSLS"].str[7:10]) \
            [["kdkec", "kddesa", "Kecamatan", "Kelurahan_Desa"]].drop_duplicates(["kdkec", "kddesa"]) \
            .set_index(["kdkec", "kddesa"])
    tambahan = 0
    for idsls in sorted(set(u["idsls"]) - set(rows)):
        kc, ds = idsls[4:7], idsls[7:10]
        nk, nd = (nama.loc[(kc, ds)].tolist() if (kc, ds) in nama.index else (kc, ds))
        rows[idsls] = dict(idsls=idsls, nmsls=f"SLS {idsls[10:14]} (tidak ada di peta)", kdkec=kc,
                           nmkec=bersih(nk), kddesa=ds, nmdesa=bersih(nd), ada_peta=0)
        tambahan += 1
    pd.DataFrame(rows.values()).sort_values("idsls").to_csv(out / "wilayah.csv", index=False, lineterminator="\n")
    u.sort_values(["idsubsls", "assignment_id", "urut"]).to_csv(out / "usaha.csv", index=False, lineterminator="\n")
    print(f"      {len(u):,} usaha | {u.assignment_id.nunique():,} assignment | {len(rows)} RT/SLS (+{tambahan} tidak ada di peta)")

    # ---------- 3. titik ----------
    print("[3/4] Membaca titik geotagging ...")
    pts = json.load(open(a.titik, encoding="utf-8"))["features"]
    t = pd.DataFrame({
        "assignment_id": [f["properties"]["assignment_id"].strip().lower() for f in pts],
        "longitude": [f["geometry"]["coordinates"][0] for f in pts],
        "latitude": [f["geometry"]["coordinates"][1] for f in pts],
    })
    n_all = len(t)
    t = t.drop_duplicates("assignment_id", keep="first").reset_index(drop=True)
    minx, miny, maxx, maxy = unary_union(sub_geoms).bounds
    m = a.margin
    dalam = t.longitude.between(minx - m, maxx + m) & t.latitude.between(miny - m, maxy + m)
    pencilan = int((~dalam).sum())
    t = t[dalam].reset_index(drop=True)

    # ---------- 4. spatial join titik -> subSLS ----------
    print("[4/4] Spatial join titik ke poligon SLS ...")
    tree = STRtree(sub_geoms)
    pi, qi = tree.query(make_points(t.longitude.values, t.latitude.values), predicate="intersects")
    first = {}
    for p_, q_ in zip(pi, qi):
        first.setdefault(int(p_), sub_idsls[int(q_)])
    t["idsubsls"] = [first.get(i, "") for i in range(len(t))]
    luar_poligon = int((t.idsubsls == "").sum())
    t = t[t.idsubsls != ""].copy()
    t["idsls"] = t.idsubsls.str[:14]; t["kdkec"] = t.idsubsls.str[4:7]; t["kddesa"] = t.idsubsls.str[7:10]
    t["latitude"] = t.latitude.round(7); t["longitude"] = t.longitude.round(7)
    t[["assignment_id", "latitude", "longitude", "idsubsls", "idsls", "kdkec", "kddesa"]] \
        .to_csv(out / "titik.csv", index=False, lineterminator="\n")

    ada = set(t.assignment_id) & set(u.assignment_id)
    print("\n=== RINGKASAN ===")
    print(f"Titik di GeoJSON            : {n_all:,}")
    print(f"  dibuang (pencilan bbox)   : {pencilan}")
    print(f"  dibuang (di luar poligon) : {luar_poligon}")
    print(f"Titik dipakai               : {len(t):,}")
    print(f"  punya data usaha          : {len(ada):,}")
    print(f"  tanpa data usaha          : {len(t)-len(ada):,}")
    print(f"Usaha (baris Excel)         : {len(u):,}")
    print(f"  assignment punya titik    : {int(u.assignment_id.isin(set(t.assignment_id)).sum()):,} baris")
    print(f"  assignment tanpa titik    : {int((~u.assignment_id.isin(set(t.assignment_id))).sum()):,} baris")
    print(f"\nSelesai -> {out}")


if __name__ == "__main__":
    sys.exit(main())
