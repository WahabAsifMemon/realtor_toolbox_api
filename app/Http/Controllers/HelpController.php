<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Help;
use Illuminate\Support\Facades\Validator;


class HelpController extends Controller
{
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        $description = $request->input('description');
    
        try {
            $help = Help::first();
    
            if ($help) {
                $help->update([
                    'description' => $description
                ]);
            } else {
                Help::create([
                    'description' => $description
                ]);
            }
    
            return response()->json(['message' => 'Help created successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    
}
