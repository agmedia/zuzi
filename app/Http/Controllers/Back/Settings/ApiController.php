<?php

namespace App\Http\Controllers\Back\Settings;

use App\Helpers\Csv;
use App\Http\Controllers\Controller;
use App\Models\Back\Settings\Api\AkademskaKnjigaMk;
use App\Models\Back\Settings\Api\PlavaKrava;
use App\Models\Back\Settings\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ApiController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return view('back.settings.api.index');
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function pelion()
    {
        return view('back.settings.pelion.index');
    }


    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        $data = $this->validateTarget($request);

        $targetClass = $this->resolveTargetClass($data);

        if ($targetClass) {
            $ok = $targetClass->process($data);

            if ($ok) {
                return response()->json(['success' => $ok]);
            }
        }

        return response()->json(['error' => 'Whoops.!! Pokušajte ponovo ili kontaktirajte administratora!']);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function upload(Request $request)
    {
        $request->validate([
            'target' => 'required',
            'method' => 'required',
            'file' => 'file|mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/excel'
        ]);

        $targetClass = $this->resolveTargetClass($request->toArray());

        if ($targetClass) {
            $path = $targetClass->upload($request);

            if ($path) {
                $excel = new Csv($path, 'Xlsx');

                $ok = $targetClass->process($request->toArray(), $excel->csv->toArray());

                if ($ok) {
                    return response()->json(['success' => $ok]);
                }
            }
        }


        return response()->json(['success' => $request->toArray()]);
    }


    /**
     * @param Request $request
     * @param string  $type
     *
     * @return mixed
     */
    public function validateTarget(Request $request, string $type = 'import')
    {
        $request->validate([
            'data.target' => 'required',
            'data.method' => 'required'
        ]);

        $data = $request->input('data');

        $request->merge([
            'data' => [
                'target' => $data['target'],
                'method' => $data['method'],
                'type' => $type
            ]
        ]);

        return $request->input('data');
    }


    /**
     * @param array $data
     *
     * @return mixed
     */
    private function resolveTargetClass(array $data)
    {
        $class = null;

        if (isset($data['target'])) {
            if ($data['target'] == 'akademska-knjiga-mk') {
                $class = new AkademskaKnjigaMk();
            }
            if ($data['target'] == 'plava-krava') {
                $class = new PlavaKrava();
            }
        }

        return $class;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pelionTest(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|string|in:item-list,item-list-attrs,item-by-id,group-items,group-active,item-type,item-groups,stock-list,stock-by-item',
            'item_id' => 'nullable|integer|min:1',
            'item_group_id' => 'nullable|integer|min:1',
            'item_type' => 'nullable|string|in:T,K,U',
            'api_key' => 'nullable|string',
        ]);

        $apiKey = $data['api_key'] ?: config('services.pelion.api_key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'Nedostaje API ključ. Unesite ga u formu ili postavite PELION_API_KEY u .env (config/services.php -> pelion).'
            ], 422);
        }

        $baseUrl = rtrim(config('services.pelion.base_url', 'https://api.pelionpro.com/api/v1'), '/');
        $query = [];
        $path = '/itemList';

        switch ($data['action']) {
            case 'item-list':
                break;
            case 'item-list-attrs':
                $query['ItemAttributes'] = 'D';
                break;
            case 'item-by-id':
                if (empty($data['item_id'])) {
                    return response()->json(['error' => 'Za ovaj poziv treba ItemId.'], 422);
                }
                $query['ItemId'] = $data['item_id'];
                break;
            case 'group-items':
                if (empty($data['item_group_id'])) {
                    return response()->json(['error' => 'Za ovaj poziv treba ItemGroupId.'], 422);
                }
                $query['ItemGroupId'] = $data['item_group_id'];
                break;
            case 'group-active':
                if (empty($data['item_group_id'])) {
                    return response()->json(['error' => 'Za ovaj poziv treba ItemGroupId.'], 422);
                }
                $query['ItemGroupId'] = $data['item_group_id'];
                $query['ItemActive'] = 'D';
                break;
            case 'item-type':
                if (empty($data['item_type'])) {
                    return response()->json(['error' => 'Za ovaj poziv treba ItemType (T, K ili U).'], 422);
                }
                $query['ItemType'] = $data['item_type'];
                break;
            case 'item-groups':
                $path = '/itemGroupList';
                break;
            case 'stock-list':
                $path = '/stockList';
                break;
            case 'stock-by-item':
                if (empty($data['item_id'])) {
                    return response()->json(['error' => 'Za ovaj poziv treba ItemId.'], 422);
                }
                $path = '/stockList';
                $query['ItemId'] = $data['item_id'];
                break;
        }

        $url = $baseUrl . $path;

        try {
            $response = Http::timeout(20)
                            ->withHeaders([
                                'Content-Type' => 'application/json',
                                'X-API-KEY' => $apiKey,
                            ])
                            ->get($url, $query);

            return response()->json([
                'success' => true,
                'status' => $response->status(),
                'url' => $response->effectiveUri() ? (string) $response->effectiveUri() : $url,
                'body' => $response->json() !== null ? $response->json() : $response->body(),
            ], $response->successful() ? 200 : 422);
        } catch (\Throwable $e) {
            Log::error('Pelion API test failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Greška kod poziva Pelion API-ja: ' . $e->getMessage()
            ], 500);
        }
    }

}
