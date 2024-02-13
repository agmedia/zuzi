<?php

namespace App\Helpers;

use GuzzleHttp\Exception\RequestException;
use MailchimpMarketing\ApiClient;

/**
 *
 */
class Mailchimp
{

    /**
     * @var string
     */
    public $mailchimp;


    /**
     * Recaptcha constructor.
     */
    public function __construct()
    {
        $this->mailchimp = new ApiClient();

        $this->mailchimp->setConfig([
            'apiKey' => config('services.mailchimp.api_key'),
            'server' => config('services.mailchimp.server_prefix')
        ]);
    }


    /**
     * @return mixed
     */
    public function ping()
    {
        return $this->mailchimp->ping->get();
    }


    /**
     * @param string|null $campaign_id
     *
     * @return mixed
     */
    public function campaign(string $campaign_id = null)
    {
        if ($campaign_id) {
            return $this->mailchimp->campaigns->get($campaign_id);
        }

        return $this->mailchimp->campaigns->list();
    }


    /**
     * @param string|null $list_id
     *
     * @return mixed
     */
    public function list(string $list_id = null)
    {
        if ($list_id) {
            return $this->mailchimp->lists->getList($list_id);
        }

        return $this->mailchimp->lists->getAllLists();
    }


    /**
     * @param string      $list_id
     * @param string      $email
     * @param string|null $f_name
     * @param string|null $l_name
     *
     * @return mixed
     */
    public function addMemberToList(string $list_id, string $email, string $f_name = null, string $l_name = null)
    {
        try {
            $user_hash = md5(strtolower($email));

            return $this->mailchimp->lists->setListMember($list_id, $user_hash, [
                "email_address" => $email,
                "status_if_new" => "subscribed",
                "status"        => "subscribed",
                "merge_fields"  => [
                    "FNAME" => $f_name,
                    "LNAME" => $l_name,
                ]
            ]);
        } catch (RequestException $exception) {
            ag_log($exception->getResponse()->getReasonPhrase(), 'error');
            ag_log($exception->getMessage(), 'error');
            ag_log($exception->getResponse()->getBody(), 'error');
        }
    }
}
