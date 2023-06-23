<?php

namespace App\Http\Controllers;

use App\Mail\SendMail;
use App\Mail\WelcomeMail;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\support\Facades\Auth;
use Illuminate\support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\TokenRepository;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required',
            'email' => 'required|string',
            'phone' => 'required|string',
            'code' => 'required',
            'gender' => 'required',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string|min:6',
            // 'otp' => 'required|string', // Added validation for OTP
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existingUser = User::where('email', $request->email)->first();

        if ($existingUser && $existingUser->status == 0) {
            $existingUser->status = 1;
            $existingUser->password = Hash::make($request->password);
            $existingUser->first_name = $request->first_name;
            $existingUser->phone = $request->phone;
            $existingUser->code = $request->code;
            $existingUser->gender = $request->gender;
            $existingUser->address = $request->address;
            $existingUser->user_img = $request->user_img;
            $existingUser->latitude = $request->latitude;
            $existingUser->longitude = $request->longitude;
            $existingUser->save();

            return response()->json(['message' => 'Account activated. You can now log in'], 200);
        }

        try {
            $otpRecord = Otp::where('email', $request->email)->first();

            if (!$otpRecord || $otpRecord->otp != $request->otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP.'
                ], 400);
            }

            $user = new User([
                'first_name' => $request->first_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'code' => $request->code,
                'gender' => $request->gender,
                'otp' => $request->otp,
                'type' => $request->type,
                'address' => $request->address,
                'status' => 1,
                'user_img' => $request->user_img,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            $user->save();
            return response()->json(['message' => 'User has been registered'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }


    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            if ($user->status == 0) {
                return response()->json(['error' => 'Unauthorized: Account has been deleted.'], 401);
            }

            if ($user->type === 'admin') {
                return response()->json(['error' => 'Unauthorized: Admin access denied.'], 401);
            }

            $token = $user->createToken('UserApp')->accessToken;
            return response()->json(['message' => 'User Profile', 'token' => $token], 200);
        } else {
            return response()->json(['error' => 'Unauthorized: Invalid email or password.'], 401);
        }
    }

    function sendVerificationOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }

        if (!empty($request->register)) {
            $user = User::where('email', $request->email)->first();
            if (!empty($user)) {
                return response()->json(['status' => 'error', 'message' => 'email already taken!'], 400);
            }
        }
        if (!empty($request->forget)) {
            $user = User::where('email', $request->email)->first();
            if (empty($user)) {
                return response()->json(['status' => 'error', 'message' => 'email not found!'], 400);
            }
        }

        $otp = rand(1000, 9999);

        try {
            $existingOtpRecord = Otp::where('email', $request->email)->first();

            if ($existingOtpRecord) {
                $existingOtpRecord->otp = $otp;
                $existingOtpRecord->save();
            } else {
                $otpRecord = new Otp();
                $otpRecord->email = $request->email;
                $otpRecord->otp = $otp;
                $otpRecord->save();
            }


            $data = [
                'otp' => $otp,
                'message' => 'Your OTP Code For Verify Email'
            ];

            $view = view('verify', compact('data'))->render();
            $mailData = [
                'subject' => 'OTP Code',
                'to' => $request->email,
                'view' => $view
            ];

            $user = User::where('email', $request->email)->first();
            if ($user) {
                $user->otp = $otp;
                $user->save();
            }

            // $this->sendMail($mailData);
            Mail::to($request->email)->send(new WelcomeMail($data));
            return response()->json(['status' => 'success', 'message' => 'Email sent successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    public function profile()
    {
        try {
            $user = auth()->user();
            if (!$user) {
                throw new \Exception('User not found.');
            }
            return response()->json([
                'success' => true,
                'message' => 'Data fetched successfully.',
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        }
    }

    public function user_update(Request $request)
    {
        try {
            $input = $request->all();
            $rules = array(
                'id' => "required",
            );
            $validator = Validator::make($input, $rules);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please see errors parameter for all errors.',
                    'errors' => $validator->errors()
                ], 400);
            } else {
                User::Where('id', $request->id)->update($input);
                return response()->json(['success' => true, 'messsage' => 'User update successfully'], 200);
            }

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'messsage' => $e->getMessage()], 403);
        }
    }

    public function changePass(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'new_password' => 'required|min:8',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $user = Auth::guard('api')->user();

        if (!Hash::check($request->input('old_password'), $user->password)) {
            return response()->json([
                "status" => false,
                "message" => "Check your old password.",
            ], 400);
        }

        if (Hash::check($request->input('new_password'), $user->password)) {
            return response()->json([
                "status" => false,
                "message" => "Please enter a password that is not similar to the current password.",
            ], 400);
        }

        try {
            $user->update(['password' => Hash::make($request->input('new_password'))]);
            return response()->json([
                "status" => true,
                "message" => "Password updated successfully.",
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    public function logout(Request $request)
    {
        $access_token = auth()->user()->token();
        $tokenRepository = app(TokenRepository::class);
        $tokenRepository->revokeAccessToken($access_token->id);

        return response()->json([
            'message' => 'User logout successfully.',
            'status' => true,
        ]);
    }

    function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }

        $otp = rand(pow(10, 4 - 1), pow(10, 4) - 1);
        $data = array(
            'otp' => $otp,
            'message' => 'Your Otp Code For Forget Password'
        );

        try {
            $existingOtpRecord = Otp::where('email', $request->email)->first();

            if ($existingOtpRecord) {
                $existingOtpRecord->otp = $otp;
                $existingOtpRecord->save();
            } else {
                $otpRecord = new Otp();
                $otpRecord->email = $request->email;
                $otpRecord->otp = $otp;
                $otpRecord->save();
            }

            Mail::to($request->email)->send(new SendMail($data));

            return response()->json(['status' => 'success', 'message' => 'Email sent successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function forgetPass(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required',
            'email' => 'required',
            'password' => 'required|min:6|max:100',
            'confirm_password' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }

        try {
            $otpRecord = Otp::where('email', $request->email)->first();
            if (!$otpRecord) {
                return response()->json(['message' => 'User not found'], 404);
            }

            if ($otpRecord->otp != $request->otp) {
                return response()->json(['message' => 'OTP does not match'], 400);
            }

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $user->update([
                'password' => Hash::make($request->password)
            ]);
            $otpRecord->otp = $request->otp;
            $otpRecord->update();

            return response()->json(['message' => 'Forget Password Successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }


    public function delete(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }


        if ($user->status == 1) {
            $user->status = 0;
            $user->save();
            return response()->json(['message' => 'Account deleted successfully', 'status' => $user->status]);
        } else {
            $user->status = 1;
            $user->save();
            return response()->json(['message' => 'User account registration enabled', 'status' => $user->status]);
        }
    }

    public function uploadImage(Request $request)
    {
        // return $request->all();
        $validator = Validator::make($request->all(), [
            'image' => 'required|image:jpeg,png,jpg,gif,svg'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $uploadFolder = 'uploads';
        $image = $request->file('image');
        $image_uploaded_path = $image->store($uploadFolder, 'public');
        $uploadedImageResponse = array(
            "image_name" => basename($image_uploaded_path),
            // "image_url" => Storage::disk('public')->url($image_uploaded_path),
            "image_url" => env('APP_URL') . "/uploads/" . basename($image_uploaded_path),
            "mime" => $image->getClientMimeType()
        );
        return response()->json(['message' => 'File Uploaded Successfully', 'data' => $uploadedImageResponse], 200);
    }


    public function react_image_upload(Request $request)
    {
        $image = $request->image;
        $image = str_replace('data:image/png;base64,', '', $image);
        $image = str_replace(' ', '+', $image);
        $mytime = Carbon::now();
        $imageName = $mytime->toDateTimeString() . '.png';
        $imName = str_replace('_', ' ', $imageName);
        $cImage = base64_decode($image);

        Storage::disk('local')->put($imName, base64_decode($image));

        $uploadedImageResponse = array(
            "image_name" => basename($imName),
            "image_url" => env('APP_URL') . "/uploads/" . basename($imName),
        );
        return response()->json(['message' => 'File Uploaded Successfully', 'data' => $uploadedImageResponse], 200);
    }
    
}