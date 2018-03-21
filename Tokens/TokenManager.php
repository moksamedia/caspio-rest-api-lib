<?php

namespace Caspio\Tokens;
use Caspio\Errors\Error as Error;
use \Httpful\Request as Request;
use SebastianBergmann\Exporter\Exception;

class TokenManager
{
    const OPTION_NAME = 'caspio_api_token_json';

    public $client_id;
    public $client_secret;
    public $oauth_path;
    public $resource_path;
    public $caspio_tables_url;
    public $access_token;
    public $refresh_token;

    public function __construct($oauth_path, $resource_path, $client_id, $client_secret)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->oauth_path = $oauth_path;
        $this->resource_path = $resource_path;

        $option = get_option(self::OPTION_NAME);

        if ($option) {
            $this->access_token = $option['accessToken'];
            $this->refresh_token = $option['refreshToken'];
            $this->expires = $option['expires'];
        }

    }

    public function storeTokenOptions() {
        self::storeTokenOption($this->access_token, $this->refresh_token, $this->expires);
    }

    public static function storeTokenOption($accessToken, $refreshToken, $expires) {

        $result = update_option(
            self::OPTION_NAME, [
            'accessToken' => $accessToken,
            'refreshToken'=> $refreshToken,
            'created' => time(),
            'expires' => time() + $expires
        ]);

        if ($result) error_log("caspio_api_token_json udpated");
        else error_log("caspio_api_token_json NOT udpated");

    }

    public function getToken() {

        if (!$this->isAccessTokenValid()) {
            if ($this->refresh_token) {
                if (!$this->renewAccessToken()) {
                    if (!$this->requestAccessTokenFromServer()) {
                        throw new Exception("Failed to get access token from caspio");
                    }
                }
            }
            else {
                if (!$this->requestAccessTokenFromServer()) {
                    throw new Exception("Failed to get access token from caspio");
                }
            }
        }

        return $this->access_token;

    }

    public function requestAccessTokenFromServer()
    {

        $client_id = $this->client_id;
        $client_secret = $this->client_secret;
        $oauth_path = $this->oauth_path;

        $body = array('grant_type'=>'client_credentials');
        $encode_creds = base64_encode(sprintf('%1$s:%2$s', $client_id, $client_secret));
        $response = Request::post($oauth_path)
            ->addHeader('Content-Type','application/x-www-form-urlencoded')
            ->addHeader('Authorization','Basic '.$encode_creds)
            ->body(http_build_query($body))
            ->send();
        if ($response->code == 200) {
            $this->access_token = $response->body->access_token;
            $this->refresh_token = $response->body->refresh_token;
            $this->expires = $response->body->expires_in + time();
            $this->storeTokenOptions();
            error_log("Caspio access token created: ".$this->access_token);
            return true;
        }
        else {
            error_log("Failed to create access token");
            return false;
        }
    }

    public function renewAccessToken()
    {
        $refresh_url = $this->oauth_path;
        $refresh_token = $this->refresh_token;
        $client_id = $this->client_id;
        $client_secret = $this->client_secret;

        $body = array('grant_type'=>'refresh_token','refresh_token'=>$refresh_token);
        $encode_creds = base64_encode(sprintf('%1$s:%2$s', $client_id, $client_secret));
        $response = Request::post($refresh_url)
            ->addHeader('Content-Type','application/x-www-form-urlencoded')
            ->addHeader('Authorization','Basic '.$encode_creds)
            ->body(http_build_query($body))
            ->send();

        if ($response->code == 200) {
            $this->access_token = $response->body->access_token;
            $this->refresh_token = $response->body->refresh_token;
            $this->expires = $response->body->expires_in + time();
            $this->storeTokenOptions();
            error_log("Caspio access token renewed: ".$this->access_token);
            return true;
        }
        else {
            error_log("Failed to renew access token");
            return false;
        }
    }

    public function isAccessTokenValid()
    {

        if (!$this->access_token) {
            return false;
        }

        $response = Request::get($this->resource_path."/tables")
            ->addHeader('Authorization','Bearer '.$this->access_token)
            ->send();

        if ($response->code == 200) {
            error_log("Caspio access token validated");
            return true;
        }
        else {
            error_log("Caspio access token NOT validated");
            return false;
        }

    }
}