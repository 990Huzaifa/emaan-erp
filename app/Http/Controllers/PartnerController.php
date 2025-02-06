<?php

namespace App\Http\Controllers;

use App\Models\BusinessHasAccount;
use App\Models\ChartOfAccount;
use App\Models\City;
use App\Models\OpeningBalance;
use DB; 
use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Partner;
use App\Models\Log;
use Illuminate\Validation\Rule;
use App\Mail\UserMail;
use Illuminate\Http\Request;
use App\Models\UserHasBusiness;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Exceptions\UnauthorizedException;
use App\Notifications\GeneralNotification;

class PartnerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list partner')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);
            $isActive = $request->query('is_active');
            $searchQuery = $request->query('search');
            
            $query = Partner::orderBy('id', 'desc')
            ->join('cities', 'partners.city_id', '=', 'cities.id')
            ->select('partners.*', 'cities.name as city'); 
            if ($isActive === 'active') {
                $query = $query->where('is_active', 1);
            } elseif ($isActive === 'inactive') {
                $query = $query->where('is_active', 0);
            }
            if (!empty($searchQuery)) {
                $partnerIds = Partner::where('name', 'like', '%' . $searchQuery . '%')
                        ->orWhere('p_code', 'like', '%' . $searchQuery . '%')
                        ->pluck('id')
                        ->toArray();
    
                // Filter orders by the found user IDs
                $query = $query->whereIn('partners.id', $partnerIds);
            }
            $data = $query->paginate($perPage);

            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check only for admin
            if ($user->role != 'admin') throw new Exception('User does not have the required permission.');

            $validator = Validator::make(
                $request->all(),[
                    'name'=>'nullable|string',
                    'city_id'=>'nullable|exists:cities,id',
                    'email'=>'required|email|string|unique:partners,email',
                    'cnic' => 'nullable|string',
                    'cnic_front' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                    'cnic_back' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                    'phone' => 'nullable|string',
                    'avatar'=>'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                    'address'=>'required|string',
                    'opening_balance'=>'nullable|numeric',

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'This email address is already in use.',

                'cnic.string'=>'CNIC must be a string',

                'cnic_front.image'=>'CNIC Front must be an image',
                'cnic_back.image'=>'CNIC Back must be an image',

                'phone.required'=>'Phone is required',
                'phone.string'=>'Phone must be a string',
                
                'city_id.required'=>'City is required',
                'city_id.exists'=>'City is not valid',

                'address.required'=>'Address is required',
                'opening_balance.numeric'=>'Opening Balance must be a number',

                'avatar.image'=>'Avatar must be an image',
                
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);

            // images upload
            $avatar=null;
            $cnic_front=null;
            $cnic_back=null;
            if ($request->hasFile('avatar')) {
                $image = $request->file('avatar');
                $image_name = 'avatar' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('partner-avatar'), $image_name);
                $avatar = 'partner-avatar/' . $image_name;
            }
            if ($request->hasFile('cnic_front')) {
                $front_image = $request->file('cnic_front');
                $front_image_name = 'cnic_' . '_front.' . $front_image->getClientOriginalExtension();
                $front_image->move(public_path('partner-cnic'), $front_image_name);
                $cnic_front = 'partner-cnic/' . $front_image_name;
            }
            if ($request->hasFile('cnic_back')) {
                $back_image = $request->file('cnic_back');
                $back_image_name = 'cnic_' . '_back.' . $back_image->getClientOriginalExtension();
                $back_image->move(public_path('partner-cnic'), $back_image_name);
                $cnic_back = 'partner-cnic/' . $back_image_name;
            }
            $cnic_images = [$cnic_front, $cnic_back];

            // unique code
            do {
                $p_code = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (Partner::where('p_code', $p_code)->exists());

            // validate coa
            $acc = ChartOfAccount::Where('name','EQUITY')->first();
            if(empty($acc)) throw new Exception('Customer COA not found', 404);

            // validate city
            $city = City::find($request->city_id);
            if(empty($city)) throw new Exception('City not found', 404);
            $COA = createCOA($request->name,$acc->code);

            $partner = Partner::create([
                'name'=>$request->name,
                'business_id'=>$request->business_id,
                'acc_id'=>$COA->id,
                'city_id'=>$request->city_id,
                'email'=>$request->email,
                'phone' => $request->phone,
                'p_code'=>$p_code,
                'cnic'=>$request->cnic,
                'cnic_images'=> json_encode($cnic_images),
                'avatar'=>$avatar,
                'address'=>$request->address,

            ]);
            $COA->update([
                'ref_id' => $partner->id
            ]);

            OpeningBalance::create([
                'acc_id' => $COA->id,
                'amount' => $request->opening_balance ?? 0,
            ]);
            BusinessHasAccount::create([
                'business_id' => $request->business_id,
                'chart_of_account_id' => $COA->id,
            ]);

            Log::create([
                'user_id' => $user->id,
                'description' => 'User create user',
            ]);    
            DB::commit();
            return response()->json($partner);
        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'view partner')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $data = Partner::find($id);

            Log::create([
                'user_id' => $user->id,
                'description' => 'User fetch user details',
            ]);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'edit partners')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            $user = Partner::findOrFail($id);
            if (empty($user)) throw new Exception('No User found', 404);
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'nullable|string',
                    'city'=>'nullable|exists:cities,id',
                    'email' => [
                    'required',
                    'email',
                    'string',
                    Rule::unique('partners')->ignore($user->id), // Exclude current user
                ],

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',

                'email.required' => 'Email is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
                'email.unique' => 'The email has already been taken.',
                
                
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $avatar = null;
            $cnic_front = null;
            $cnic_back = null;
            if ($request->hasFile('avatar')) {
                $image = $request->file('avatar');
                $image_name = 'avatar' . time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('user-avatar'), $image_name);
                $avatar = 'user-avatar/' . $image_name;
            }
            if ($request->hasFile('cnic_front')) {
                $front_image = $request->file('cnic_front');
                $front_image_name = 'cnic_' . $user->id . '_front.' . $front_image->getClientOriginalExtension();
                $front_image->move(public_path('user-cnic'), $front_image_name);
                $cnic_front = 'user-cnic/' . $front_image_name;
            }
            if ($request->hasFile('cnic_back')) {
                $back_image = $request->file('cnic_back');
                $back_image_name = 'cnic_' . $user->id . '_back.' . $back_image->getClientOriginalExtension();
                $back_image->move(public_path('user-cnic'), $back_image_name);
                $cnic_back = 'user-cnic/' . $back_image_name;
            }
            $cnic_images = [$cnic_front, $cnic_back];
            $user->update([
                    'email' =>$request->email,
                    'name'=> $request->name,
                    'city_id'=>$request->city_id ?? $user->city_id,
                    'phone'=>$request->phone ?? null,
                    'address' => $request->address ?? null,
                    'cnic'=>$request->cnic ?? null,
                    'cnic_images'=> $cnic_images,
                    'avatar' => $avatar,
            ]);
            if($request->email != $user->email){
                // sending mail to user
                Mail::to($request->email)->send(new UserMail([
                    'message'=> 'your Email has beed updated your new email is '.$request->email,
                    'url' => config('frontend.url'),
                    'is_url'=>true,
                ]));
            }
            
            $str_permissions = $request->input('permissions');

            // Check if permissions is a JSON string and decode it
            if (is_string($str_permissions)) {
                $str_permissions = json_decode($str_permissions, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Permissions format is invalid', 400);
                }
            }
            $uhb_ids = UserHasBusiness::where('user_id', $id)->pluck('id')->toArray();

            // Delete the permissions associated with these UserHasBusiness records
            DB::table('model_has_permissions')->whereIn('model_id', $uhb_ids)
                ->where('model_type', (new UserHasBusiness())->getMorphClass())
                ->delete();
            
            // Now delete the UserHasBusiness records
            UserHasBusiness::where('user_id', $id)->delete();
            
            foreach ($str_permissions as $business_id => $permissions) {
                
                    $uhb = UserHasBusiness::create([
                        'user_id' => $id,
                        'business_id' => $business_id,
                    ]);
                
            
                // Add "edit profile" permission if not already in the list
                if (!in_array('edit profile', $permissions)) {
                    $permissions[] = 'edit profile';
                }
            
                // Sync the permissions
                $uhb->syncPermissions($permissions);
            }

            
            Log::create([
                'user_id' => $user->id,
                'description' => 'User update user details with permissions',
            ]);
            return response()->json(['success'=>'user updated successfully.'],200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function list(): JsonResponse
    {
        try{
            $user = Auth::user();
            
            // Check if the user has the required permission
            if ($user->role != 'admin') {
                $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list partner')) {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }

            // Fetch users based on the retrieved user IDs
            $data = Partner::select('id', 'name', 'acc_id')
                ->get();
        
            Log::create([
                'user_id' => $user->id,
                'description' => 'User fetch invite list of users',
            ]);
            return response()->json($data,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

}
