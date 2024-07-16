<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB ;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{

    public function crear(Request $request){
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed'
        ]);
    
        $usuario = User::create($request->all());
        $usuario->syncRoles('usuario');
        return response()->json([
            'data' => $usuario, 'message' => 'Usuario guardado con éxito'
        ], 200);
    }

    

    function verusuarios()
    {
        $usuarios = User::all();
        return response()->json(['data' => $usuarios]);
    }


    function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|'
        ]);
        $credentials = $request->only('email', 'password');


        if (Auth::attempt($credentials)) {

            $request->session()->regenerate();

            return response()->json([
                'data' => Auth::user(), 'code' => 200

            ]);
        }


        return response()->json(['data' => 'El email o la contraseña no son correctas.', 'code' => 422]);
    }

    public function addPermissionsToRole(Request $request)
    {
        $request->validate([
            'role' => 'required',
            'permission' => 'required'
        ]);
        $role = Role::find($request->role);
        $role->hasPermissionTo($request->permission);
        return response()->json(['data' => $role]);
    }

    public function get_user(Request $request)
    {
        $id = $request->user()->id;
        $user = User::find($id);
        $user->getPermissionsViaRoles();

        return response()->json(['data' => $user]);
    }

    public function cerrarSesion(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function auth(Request $request)
    {
        return Auth::check();
    }

    public function enviarRecuperarContraseña(Request $request)
    {

        try {

            $request->validate([
                'email' => 'required|email|exists:users',
            ]);


            $token = Str::random(64);


            DB::table('password_reset_tokens')->where('email', $request->email)->delete();


            DB::table('password_reset_tokens')->insert([
                'email' => $request->email,
                'token' => $token,
                'created_at' => Carbon::now()
            ]);


            Mail::send('email.recuperar-contrasenia', ['token' => $token], function ($message) use ($request) {
                $message->to($request->email);
                $message->subject('Recuperar Contraseña');
            });

            return response()->json(['message' => 'Te hemos enviado un email']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ocurrió un error al procesar la solicitud.'], 500);
        }
    }

    public function formularioActualizacion($token)
    {
        return view('formulario-actualizacion', ['token' => $token]);
    }


    public function actualizarContrasenia(Request $request)
    {

        $request->validate([
            'email' => 'required|email|exists:users',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required'
        ]);

        $updatePassword = DB::table('password_reset_tokens')
            ->where([
                'email' => $request->email,
                'token' => $request->token
            ])
            ->first();


        if (!$updatePassword) {
            return back()->withInput()->with('error', 'Token inválido');
        }

        $user = User::where('email', $request->email)
            ->update(['password' => Hash::make($request->password)]);
        DB::table('password_reset_tokens')->where(['email' => $request->email])->delete();

        return redirect('/login')->with('message', 'Tu contraseña se ha cambiado correctamente');
    }


    public function addRole(User $user, Request $request)
    {
        $user->syncRoles($request->all());

        return response()->json(['user' => $user]);
    }

    public function crearUsuariosRoles(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed',
            'role' => 'required|string'
        ]);

        return DB::transaction(function() use($request){
            $user = User::create($request->only(['email', 'name']) + [
                'password' => bcrypt('password')
            ]);
            $user->assignRole($request->input('role'));
    
            return response()->json(['data' => 'bien']);
        });
       
    }

    public function getUsersRoles(){
        $users = User::WithoutRole('usuario')->get();
        
        $users->transform(function($user) {
            
            $roles = $user->getRoleNames();
            
            $user->roles = $roles;
            
            return $user;
        });
        
        return response()->json(['data' => $users]);
    }

    public function deleteprueba(Request $request){
        $user = User::find($request->input('id'));
        $user->delete();
        return response()->json(['data'=> $user]);
    }
}
