<?php

namespace App\Http\Controllers;

use App\Models\UsuarioCotizacion;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpParser\Node\Expr\New_;

class UsuarioCotizacionController extends Controller
{
    public function cotizar(Request $request)
    {
        $request->validate([
            'file' => 'file|required',
        ]);
        try {
            $response = $this->apiRequest($request->file('file'));
            $filePath = $request->file('file')->store('files');

            $cotizacion = UsuarioCotizacion::create([
                'path' => $filePath,
                'nombre' => $request->file('file')->getClientOriginalName(),
                'minutos' => ($response['estimated_printing_time_seconds'] / 60) / 3.5,
                'precio' => (($response['estimated_printing_time_seconds'] / 60) / 3.5) * 1.5,
                'usuario_id' => Auth::user()->id,

            ]);

            return response()->json(['data' => $cotizacion]);

        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
        
    }

    public function apiRequest($file)
    {
        $url = "https://3d-print-stl-estimation.p.rapidapi.com/slice_and_extract?rotate_y=0&rotate_x=0&config_file=config.ini";

        $headers = [
            'x-rapidapi-host' => '3d-print-stl-estimation.p.rapidapi.com',
            'x-rapidapi-key' => '987632cf0emsh756b8484307fe63p130018jsnf063dff1b8f7',
        ];

        $client = new \GuzzleHttp\Client();

        $response = $client->post($url, [
            'headers' => $headers,
            'multipart' => [
                [
                    'name'     => 'stl_file',
                    'contents' => fopen($file->getRealPath(), 'r'),
                    'filename' => $file->getClientOriginalName()
                ]
            ]
        ]);

        if ($response->getStatusCode() != 200) {
            throw new Exception('Ocurrió un problema al procesar el archivo: ' . $response->getBody()->getContents());
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function index() 
    {
        //solamente puede devolver-imprimir archivos que tengan maximo 1 mes de cotizado
        // @todo: identificar el tiempo de validez de una cotizacion
        
        return response()->json([
            'data' => UsuarioCotizacion::where('usuario_id', Auth::user()->id)->get(),
        ]); 
    }


    public function store()
    {
    
    }
}
