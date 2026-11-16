<?php

namespace App\Http\Controllers;

use App\Models\BeritaKegiatan;
use App\Models\JenisLaporan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BeritaKegiatanController extends Controller
{
    public function index()
    {
        $data = BeritaKegiatan::with(['user', 'jenisLaporan'])->latest()->get();
        return view('backend.berita.index', [
            'data'  => $data,
            'title' => "Berita Kegiatan",
        ]);
    }

    public function create()
    {
        $jenisLaporan = JenisLaporan::all();
        return view('backend.berita.create', [
            'title' => "Tambah Berita Kegiatan",
            'jenisLaporan' => $jenisLaporan
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'id_jenis_laporan' => 'required',
            'tanggal' => 'required|date',
            'tempat' => 'required',
            'jumlah_peserta' => 'required|integer',
            'gambar.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            'link_pdf.*' => 'mimes:pdf|max:10000',
        ]);

        $input = $request->except(['gambar', 'link_pdf']); // Ambil input selain file dulu
        $input['user_id'] = auth()->id();

        // Upload Gambar
        if ($request->hasFile('gambar')) {
            $images = [];
            foreach ($request->file('gambar') as $file) {
                $name = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('berita/images', $name, 'public');
                $images[] = $path;
            }
            $input['gambar'] = $images;
        }

        // Upload PDF
        if ($request->hasFile('link_pdf')) {
            $pdfs = [];
            foreach ($request->file('link_pdf') as $file) {
                $name = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('berita/documents', $name, 'public');
                $pdfs[] = $path;
            }
            $input['link_pdf'] = $pdfs;
        }

        BeritaKegiatan::create($input);

        return redirect()->route('berita.index')->with('success', 'Berita berhasil disimpan!');
    }
}
