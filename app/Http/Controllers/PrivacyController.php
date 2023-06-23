<?php

namespace App\Http\Controllers;

use Faker\Provider\Lorem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Privacy;


class PrivacyController extends Controller
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
            $privacy = Privacy::first();
    
            if ($privacy) {
                $privacy->update([
                    'description' => $description
                ]);
            } else {
                Privacy::create([
                    'description' => $description
                ]);
            }
    
            return response()->json(['message' => 'Privacy  Created successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    
}
