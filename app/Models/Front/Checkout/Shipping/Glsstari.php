<?php

namespace App\Models\Front\Checkout\Shipping;

use App\Models\Back\Orders\Order;
use SoapClient;
use \stdClass;

/**
 * Class Cod
 * @package App\Models\Front\Checkout\Payment
 */
class Glsstari
{

    /**
     * @var int
     */
    private $order;


    /**
     * Cod constructor.
     *
     * @param $order
     */
    public function __construct($order)
    {
        $this->order = $order;
    }


    public function resolve()
    {
        try {
            //These parameters are needed to be optimalise depending on the environment:
            ini_set('memory_limit', '1024M');
            ini_set('max_execution_time', 600);

            $clientNumber = (int) config('services.gls.client_number');
            $username = (string) config('services.gls.username');
            $pwd = (string) config('services.gls.password');
            $password = hash('sha512', $pwd, true);

            $brojracuna = $this->order['id'];

            $komentar = $this->order['comment'];

            $idmjesta = substr($komentar, strpos($komentar, "_") + 1);

            $parcels                 = [];
            $parcel                  = new StdClass();
            $parcel->ClientNumber    = $clientNumber;
            $parcel->ClientReference = $brojracuna;
            $parcel->CODAmount       = $this->getTotal();
            $parcel->CODReference    = $brojracuna;
            // $parcel->Content = "CONTENT";
            $parcel->Count                    = 1;
            $deliveryAddress                  = new StdClass();
            $deliveryAddress->ContactEmail    = $this->order['payment_email'];
            $deliveryAddress->ContactName     = $this->order['payment_fname'] . ' ' . $this->order['payment_lname'];
            $deliveryAddress->ContactPhone    = $this->order['payment_phone'];
            $deliveryAddress->Name            = $this->order['payment_fname'] . ' ' . $this->order['payment_lname'];
            $deliveryAddress->Street          = $this->order['payment_address'];
            $deliveryAddress->HouseNumber     = "";
            $deliveryAddress->City            = $this->order['payment_city'];
            $deliveryAddress->ZipCode         = $this->order['payment_zip'];
            $deliveryAddress->CountryIsoCode  = "HR";
            $deliveryAddress->HouseNumberInfo = "";
            $parcel->DeliveryAddress          = $deliveryAddress;
            $pickupAddress                    = new StdClass();
            $pickupAddress->ContactName       = "Mirjana Vulić Šaldić";
            $pickupAddress->ContactPhone      = "+385916047126";
            $pickupAddress->ContactEmail      = "info@zuzi.hr";
            $pickupAddress->Name              = "Zuzi Obrt";
            $pickupAddress->Street            = "Antuna Šoljana";
            $pickupAddress->HouseNumber       = "33";
            $pickupAddress->City              = "Zagreb";
            $pickupAddress->ZipCode           = "10000";
            $pickupAddress->CountryIsoCode    = "HR";
            $pickupAddress->HouseNumberInfo   = "";
            $parcel->PickupAddress            = $pickupAddress;
            $parcel->PickupDate               = date('Y-m-d');
            if( $this->order['shipping_code']=='gls_paketomat'){
                $service1 = new StdClass();
                $service1->Code = "PSD";
                $parameter1 = new StdClass();
                $parameter1->StringValue = $idmjesta;
                $service1->PSDParameter = $parameter1;
                $services = [];
                $services[] = $service1;
                $parcel->ServiceList = $services;
            }
            $parcels[] = $parcel;

            //The service URL:
            $wsdl = str_replace('ParcelService', 'SERVICE_NAME', (string) config('services.gls.wsdl'));

            $soapOptions = array('soap_version' => SOAP_1_1);

            if (file_exists(base_path('cacert.pem'))) {
                $soapOptions['stream_context'] = stream_context_create(array('ssl' => array('cafile' => base_path('cacert.pem'))));
            }

            //Parcel service:
            $serviceName = "ParcelService";

            return $this->PrintLabels($username, $password, $parcels, str_replace("SERVICE_NAME", $serviceName, $wsdl), $soapOptions, $this->order);

        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }


    private function getTotal()
    {
        if ($this->order['payment_code'] == 'cod') {
            $mani = $this->order['total'];
            $mani = number_format((float) $mani, 2, '.', '');

        } else {
            $mani = 0;
        }

        return $mani;
    }


    /**
     * Label(s) generation by the service.
     *
     * @param $username
     * @param $password
     * @param $parcels
     * @param $wsdl
     * @param $soapOptions
     *
     * @return void
     */
    private function PrintLabels($username, $password, $parcels, $wsdl, $soapOptions, $order)
    {
        //Test request:
        $printLabelsRequest = array('Username'   => $username,
            'Password'   => $password,
            'ParcelList' => $parcels,
            'WebshopEngine' => 'Zuzi',
            'PrintPosition' => 1,
            'ShowPrintDialog' => 0,
            'TypeOfPrinter' => config('services.gls.printer_type', 'A4_2x2'));

        $request = array("printLabelsRequest" => $printLabelsRequest);

        //Service client creation:
        $client = new SoapClient($wsdl, $soapOptions);

        //Service calling:
        $response = $client->PrintLabels($request);
        $printLabelsResult = $response->PrintLabelsResult ?? null;
        $printLabelsErrors = json_decode(json_encode($printLabelsResult->PrintLabelsErrorList ?? []), true) ?: [];
        $printLabelsInfoList = json_decode(json_encode($printLabelsResult->PrintLabelsInfoList ?? []), true) ?: [];

        $parcelIdList = [];
        $parcelNumberList = [];
        $printLabelsInfo = $printLabelsInfoList['PrintLabelsInfo'] ?? $printLabelsInfoList;

        if (isset($printLabelsInfo['ParcelId']) || isset($printLabelsInfo['ParcelNumber'])) {
            $printLabelsInfo = [$printLabelsInfo];
        }

        foreach ($printLabelsInfo as $info) {
            if (! is_array($info)) {
                continue;
            }

            if (! empty($info['ParcelId'])) {
                $parcelIdList[] = $info['ParcelId'];
            }

            if (! empty($info['ParcelNumber'])) {
                $parcelNumberList[] = $info['ParcelNumber'];
            }
        }

        if ($response != null && count($printLabelsErrors) == 0 && count($parcelNumberList) > 0) {
            $order->update(['printed' => 1]);
        }

        return [
            'ParcelIdList' => $parcelIdList,
            'ParcelNumberList' => $parcelNumberList,
            'PrintLabelsInfoList' => $printLabelsInfoList,
            'PrintLabelsErrorList' => $printLabelsErrors,
            'Labels' => $printLabelsResult->Labels ?? null,
        ];
    }


    /**
     * Preparing label(s) by the service.
     *
     * @param $username
     * @param $password
     * @param $parcels
     * @param $wsdl
     * @param $soapOptions
     * @param $order
     *
     * @return array
     */
    private function PrepareLabels($username, $password, $parcels, $wsdl, $soapOptions, $order)
    {
        //Test request:
        $prepareLabelsRequest = array('Username'   => $username,
            'Password'   => $password,
            'ParcelList' => $parcels);

        $request = array("prepareLabelsRequest" => $prepareLabelsRequest);

        //Service client creation:
        $client = new SoapClient($wsdl, $soapOptions);

        //Service calling:
        $response = $client->PrepareLabels($request);
        $prepareLabelsResult = $response->PrepareLabelsResult ?? null;
        $prepareLabelsErrors = json_decode(json_encode($prepareLabelsResult->PrepareLabelsError ?? []), true) ?: [];
        $parcelInfoList = json_decode(json_encode($prepareLabelsResult->ParcelInfoList ?? []), true) ?: [];

        $parcelIdList = [];
        if ($response != null && count($prepareLabelsErrors) == 0 && count($parcelInfoList) > 0) {
            $parcelId = data_get($parcelInfoList, 'ParcelInfo.ParcelId');

            if ($parcelId) {
                $parcelIdList[] = $parcelId;
                $order->update(['printed' => 1]);
            }
        }

        //Test request:
        $getPrintedLabelsRequest = array('Username'        => $username,
            'Password'        => $password,
            'ParcelIdList'    => $parcelIdList,
            'PrintPosition'   => 1,
            'ShowPrintDialog' => 0,
            'PrepareLabelsError' => $prepareLabelsErrors,
            'ParcelInfoList' => $parcelInfoList);

        return $getPrintedLabelsRequest;
    }


    /**
     * Get label(s) by the service.
     *
     * @param $wsdl
     * @param $soapOptions
     * @param $getPrintedLabelsRequest
     *
     * @return void
     */
    private function GetPrintedLabels($wsdl, $soapOptions, $getPrintedLabelsRequest)
    {
        $request = array("getPrintedLabelsRequest" => $getPrintedLabelsRequest);

        //Service client creation:
        $client = new SoapClient($wsdl, $soapOptions);

        //Service calling:
        $response = $client->GetPrintedLabels($request);

        if ($response != null && count((array) $response->GetPrintedLabelsResult->GetPrintedLabelsErrorList) == 0 && $response->GetPrintedLabelsResult->Labels != "") {
            //Label(s) saving:
            file_put_contents('php_soap_client_GetPrintedLabels.pdf', $response->GetPrintedLabelsResult->Labels);
        }
    }


    /**
     * Get parcel(s) information by date ranges.
     *
     * @param $username
     * @param $password
     * @param $wsdl
     * @param $soapOptions
     *
     * @return void
     */
    private function GetParcelList($username, $password, $wsdl, $soapOptions)
    {
        //Test request:
        $getParcelListRequest = array('Username'       => $username,
            'Password'       => $password,
            'PickupDateFrom' => '2020-04-16',
            'PickupDateTo'   => '2020-04-16',
            'PrintDateFrom'  => null,
            'PrintDateTo'    => null);

        $request = array("getParcelListRequest" => $getParcelListRequest);

        //Service client creation:
        $client = new SoapClient($wsdl, $soapOptions);

        //Service calling:
        $response = $client->GetParcelList($request);

        var_dump(count((array) $response->GetParcelListResult->GetParcelListErrors));
        var_dump(count((array) $response->GetParcelListResult->PrintDataInfoList));
    }


    /**
     * Get parcel statuses.
     *
     * @param $username
     * @param $password
     * @param $wsdl
     * @param $soapOptions
     *
     * @return void
     */
    private function GetParcelStatuses($username, $password, $wsdl, $soapOptions)
    {
        //Test request:
        $getParcelStatusesRequest = array('Username'        => $username,
            'Password'        => $password,
            'ParcelNumber'    => 0,
            'ReturnPOD'       => true,
            'LanguageIsoCode' => "HR");

        $request = array("getParcelStatusesRequest" => $getParcelStatusesRequest);

        //Service client creation:
        $client = new SoapClient($wsdl, $soapOptions);

        //Service calling:
        $response = $client->GetParcelStatuses($request);

        if ($response != null) {
            var_dump(count((array) $response->GetParcelStatusesResult->GetParcelStatusErrors));
            if (count((array) $response->GetParcelStatusesResult->GetParcelStatusErrors) == 0 && $response->GetParcelStatusesResult->POD != "") {
                //POD saving:
                file_put_contents('php_soap_client_GetParcelStatuses.pdf', $response->GetParcelStatusesResult->POD);
            }
        }
    }
}
