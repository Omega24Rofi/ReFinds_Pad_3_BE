<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AccountVerification;
use App\Models\RefindsUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\AccountVerificationMail;
use Carbon\Carbon;

class AccountVerificationController extends Controller
{
    /**
     * fungsi ini adalah untuk membuat token verivikasi dan mengirim email berisi link verifikasi
     * parameternya adalah:
     * $request -> email
     */

    public function sendVerificationToken($id_user)
    {
        try {
            // Cari user berdasarkan id_user
            $user = RefindsUser::find($id_user);

            // Periksa apakah user ada
            if (!$user) {
                return response()->json([
                    'message' => 'User not found.',
                    'result' => 'error',
                ], 404);
            }

            // Cek jika ada token yang masih berlaku dan hapus token lama
            $existingVerification = AccountVerification::where('id_user', $user->id_user)
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if ($existingVerification) {
                // Token masih berlaku, hapus token yang lama
                $existingVerification->delete();
            }

            // Buat token verifikasi baru
            $token = Str::random(60);

            // Simpan atau update token verifikasi di database
            $verification = AccountVerification::updateOrCreate(
                ['id_user' => $user->id_user],
                [
                    'verification_token' => $token,
                    'expires_at' => Carbon::now()->addHours(24),
                    'status' => 'pending',
                ]
            );

            // Cek apakah penyimpanan berhasil
            if (!$verification) {
                return response()->json([
                    'message' => 'Failed to generate verification token.',
                    'result' => 'error',
                ], 500);
            }

            // Kirimkan token ke pengguna melalui email
            try {
                Mail::to($user->email)->send(new AccountVerificationMail($token));
            } catch (\Exception $e) {
                // Menangani error pengiriman email
                return response()->json([
                    'message' => 'Failed to send verification email.',
                    'result' => 'error',
                    'error' => $e->getMessage(),
                ], 500);
            }

            // Kembalikan response sukses
            return response()->json([
                'message' => 'Verification token generated and email sent.',
                'result' => 'success',
                'verification_token' => $token,
            ]);
        } catch (\Exception $e) {
            // Menangani exception lainnya
            return response()->json([
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



     /**
      * link untuk verifikasi akun dari email
      */
     public function verifyAccount($token_verification)
     {
         // Cari token di tabel account_verifications
         $verification = AccountVerification::where('verification_token', $token_verification)->first();

         if (!$verification) {
             return response()->json(['message' => 'Invalid verification token.'], 404);
         }

         // Cek jika token sudah kadaluarsa
         if (Carbon::now()->greaterThan($verification->expires_at)) {
             $verification->update(['status' => 'expired']);
             return response()->json(['message' => 'Verification token has expired.'], 400);
         }

         // Mendapatkan user berdasarkan id_user dari token
         $user = $verification->user; // mengambil model user terkait view fn relationship di model

         // Verifikasi akun pengguna
         $verification->update(['status' => 'verified']);
         $user->update([
             'status_akun' => 'active',
             'verification_date' => Carbon::now() // Mengisi tanggal verifikasi
         ]);

        // Mengambil URL frontend dari konfigurasi
        $frontendUrl = config('frontend.url');

        return redirect()->to($frontendUrl . '/login/' . urlencode('Verifikasi akun Anda berhasil, silahkan login.'));

     }


}
