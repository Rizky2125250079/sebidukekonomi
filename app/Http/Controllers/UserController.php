<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = User::latest()->get();
        return view('backend.users', [
            'data' => $data,
            'title' => "Manajemen Pengguna"
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', // Gunakan confirmed jika ada input password_confirmation
            'role'     => 'required|in:admin,petugas',
            'wil_tugas' => 'nullable|array', // Validasi sebagai array karena wil_tugas adalah JSON/Array
        ], [
            'name.required'  => 'Nama lengkap wajib diisi.',
            'email.unique'   => 'Email sudah terdaftar.',
            'password.min'   => 'Password minimal harus 8 karakter.',
            'role.required'  => 'Pilih role pengguna.',
        ]);

        try {
            // 2. Simpan ke Database
            User::create([
                'name'      => $request->name,
                'email'     => $request->email,
                'password'  => bcrypt($request->password), // Enkripsi password
                'role'      => $request->role,
                'wil_tugas' => $request->wil_tugas, // Laravel otomatis mengubah array ke JSON jika sudah di-cast di Model
            ]);

            return redirect()->route('users.index')->with('success', 'Pengguna baru berhasil ditambahkan!');
        } catch (\Exception $e) {
            // Jika ada error database
            return redirect()->back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
