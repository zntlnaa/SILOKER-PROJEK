<?php

namespace App\Http\Controllers;
use App\Models\Pencaker;
use App\Models\Loker;
use App\Models\Tahapan_apply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PencakerController extends Controller
{
    //Menampilkan Daftar Pencaker yang Apply di Loker Tertentu
    public function getPencaker($idloker)
    {
        // Ambil data pencaker yang apply di loker dengan ID tertentu dan memiliki tahapan "Seleksi Wawancara" atau "Seleksi Administrasi"
        $data = DB::table('pencaker')
            ->join('apply_loker', 'pencaker.noktp', '=', 'apply_loker.noktp')
            ->join('tahapan_apply', 'apply_loker.idapply', '=', 'tahapan_apply.idapply')
            ->join('tahapan', 'tahapan_apply.idtahapan', '=', 'tahapan.idtahapan')
            ->select('apply_loker.idapply as id_apply', 'pencaker.nama')
            ->addSelect(DB::raw('MAX(CASE WHEN tahapan.nama = "Seleksi Wawancara" THEN "Seleksi Wawancara" ELSE "Seleksi Administrasi" END) as tahapan'))
            ->where('apply_loker.idloker', $idloker)
            ->whereIn('tahapan.nama', ['Seleksi Wawancara', 'Seleksi Administrasi'])
            ->groupBy('apply_loker.idapply', 'pencaker.nama')
            ->orderBy('tahapan', 'asc')
            ->paginate(5);
    
        // Kirim data ke view untuk ditampilkan
        $namaLoker = Loker::where('idloker', $idloker)->value('nama');
        return view('listPencakerApply', ['data' => $data, 'namaLoker' => $namaLoker, 'idloker' => $idloker]);
    }
    

    //Menampilkan detail pencaker
    public function detailPencaker($idapply)
    {
        // Ambil data pencaker berdasarkan ID Apply
        $data = DB::table('pencaker')
            ->join('apply_loker', 'pencaker.noktp', '=', 'apply_loker.noktp')
            ->select('pencaker.*')
            ->where('apply_loker.idapply', $idapply)
            ->first(); // Mengambil hanya satu hasil
    
        return view('detailPencaker', ['data' => $data]);
    }

    public function seleksiAdministrasi($idloker)
    {
        // Dapatkan data pencaker yang telah mendaftar untuk loker ini
        $data = Pencaker::join('apply_loker', 'pencaker.noktp', '=', 'apply_loker.noktp')
            ->join('tahapan_apply', 'apply_loker.idapply', '=', 'tahapan_apply.idapply')
            ->join('tahapan', 'tahapan_apply.idtahapan', '=', 'tahapan.idtahapan')
            ->select('apply_loker.idapply as id_apply', 'pencaker.nama', 'apply_loker.idloker', 'tahapan.nama as nama_tahapan','tahapan_apply.nilai as nilai')
            ->where('apply_loker.idloker', $idloker)
            ->where('tahapan.nama', 'Seleksi Administrasi')
            ->orderBy('tahapan_apply.idapply', 'asc')
            ->paginate(5);
    
        // Kirim data ke view untuk ditampilkan
        $namaLoker = Loker::where('idloker', $idloker)->value('nama');
        return view('seleksiAdministrasi', ['data' => $data, 'namaLoker' => $namaLoker]);
    }
    
    public function lulusSeleksiAdministrasi(Request $request, $idloker)
    {
        $idapply = $request->input('id_apply');
        $nilai = $request->input('nilai');
        
        // Pastikan Anda validasi input sesuai kebutuhan, seperti memastikan nilai hanya 0 atau 1.
    
        $tahapan_apply = Tahapan_apply::where('idapply', $idapply)
            ->where('idtahapan', 1)
            ->first();
    
        if ($tahapan_apply) {
            $tahapan_apply->update(['nilai' => $nilai, 'tgl_update' => now()]);
        } else {
            Tahapan_apply::create([
                'idapply' => $idapply,
                'idtahapan' => 1,
                'nilai' => $nilai,
                'tgl_update' => now(),
            ]);
        }
    
        return redirect()->route('seleksi-administrasi', ['idloker' => $idloker])->with('success', 'Calon berhasil ditandai Lulus.');
    }
    
    public function tidakLulusSeleksiAdministrasi(Request $request, $idloker)
{
    $idapply = $request->input('id_apply');
    $nilai = $request->input('nilai');
    
    // Pastikan Anda validasi input sesuai kebutuhan, seperti memastikan nilai hanya 0 atau 1.

    // Periksa dan update atau tambahkan nilai untuk tahapan 1
    $tahapan_apply = Tahapan_apply::updateOrCreate(
        ['idapply' => $idapply, 'idtahapan' => 1],
        ['nilai' => $nilai, 'tgl_update' => now()]
    );

    // Jika nilai adalah 0, hapus baris dengan idtahapan = 2 (jika ada)
    if ($nilai == 0) {
        Tahapan_apply::where('idapply', $idapply)
            ->where('idtahapan', 2)
            ->delete();
    }

    return redirect()->route('seleksi-administrasi', ['idloker' => $idloker])->with('success', 'Calon berhasil ditandai Tidak Lulus.');
}
  
    public function seleksiWawancara($idloker)
    {
        $data = Pencaker::join('apply_loker', 'pencaker.noktp', '=', 'apply_loker.noktp')
            ->join('tahapan_apply as tahapan_apply1', 'apply_loker.idapply', '=', 'tahapan_apply1.idapply')
            ->leftJoin('tahapan_apply as tahapan_apply2', function ($join) {
                $join->on('apply_loker.idapply', '=', 'tahapan_apply2.idapply')
                    ->where('tahapan_apply2.idtahapan', 2);
            })
            ->select('apply_loker.idapply as id_apply', 'pencaker.nama', 'apply_loker.idloker', 'tahapan_apply2.nilai as nilai')
            ->where('apply_loker.idloker', $idloker)
            ->where('tahapan_apply1.idtahapan', 1) // Syarat tahapan 1
            ->where('tahapan_apply1.nilai', 1)    // Syarat nilai tahapan 1 = 1
            ->orderBy('apply_loker.idapply', 'asc')
            ->paginate(5);

    
        // Kirim data ke view untuk ditampilkan
        $namaLoker = Loker::where('idloker', $idloker)->value('nama');
        return view('seleksiWawancara', ['data' => $data, 'namaLoker' => $namaLoker]);
    }

    public function lulusSeleksiWawancara(Request $request, $idloker)
{
    $idapply = $request->input('id_apply');
    $nilai = $request->input('nilai');
    
    // Validasi input sesuai kebutuhan, misalnya memastikan nilai hanya 0 atau 1.

    $tahapan_apply1 = Tahapan_apply::where('idapply', $idapply)
        ->where('idtahapan', 1)
        ->where('nilai', 1)
        ->first();

    if ($tahapan_apply1) {
        $tahapan_apply2 = Tahapan_apply::where('idapply', $idapply)
            ->where('idtahapan', 2)
            ->first();

        if ($tahapan_apply2) {
            // Jika sudah ada, update nilai
            $tahapan_apply2->update(['nilai' => $nilai, 'tgl_update' => now()]);
        } else {
            // Jika belum ada, buat entitas baru dengan idtahapan = 2
            Tahapan_apply::create([
                'idapply' => $idapply,
                'idtahapan' => 2,
                'nilai' => $nilai,
                'tgl_update' => now(),
            ]);
        }
    }

    return redirect()->route('seleksi-wawancara', ['idloker' => $idloker])->with('success', 'Calon berhasil ditandai Lulus.');
}

    public function tidakLulusSeleksiWawancara(Request $request, $idloker)
{
    $idapply = $request->input('id_apply');
    $nilai = $request->input('nilai');
    
    // Validasi input sesuai kebutuhan, misalnya memastikan nilai hanya 0 atau 1.

    $tahapan_apply1 = Tahapan_apply::where('idapply', $idapply)
        ->where('idtahapan', 1)
        ->where('nilai', 1)
        ->first();

    if ($tahapan_apply1) {
        $tahapan_apply2 = Tahapan_apply::where('idapply', $idapply)
            ->where('idtahapan', 2)
            ->first();

        if ($tahapan_apply2) {
            // Jika sudah ada, update nilai
            $tahapan_apply2->update(['nilai' => $nilai, 'tgl_update' => now()]);
        } else {
            // Jika belum ada, buat entitas baru dengan idtahapan = 2
            Tahapan_apply::create([
                'idapply' => $idapply,
                'idtahapan' => 2,
                'nilai' => $nilai,
                'tgl_update' => now(),
            ]);
        }
    }

    return redirect()->route('seleksi-wawancara', ['idloker' => $idloker])->with('success', 'Calon berhasil ditandai Tidak Lulus.');

}
}