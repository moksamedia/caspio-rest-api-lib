<?php


namespace Caspio;

use Caspio\Tokens\TokenManager;
use Httpful\Request;

class Caspio
{
    public $tokenManager;
    public $rest_url;
    public $response_format_header = 'application/json';

    protected $logger;

    const SKBM_REST_UserInfo_Url = "https://b5.caspio.com/dp.asp?cbqe=";

    public function __construct($tokenManager, $rest_url)
    {
        $this->rest_url = $rest_url;
        $this->tokenManager = $tokenManager;

        global $logger;
        $this->logger = $logger;

    }

    public function setReponseFormat($response_type)
    {
        $response_type = strtolower($response_type);
        switch ($response_type) {
            case 'xml': 
                $this->response_format_header = 'application/xml';
                break;
            case 'json':
                $this->response_format_header = 'application/json';
                break;
            default:
                $this->response_format_header = 'application/json';
                break;
        }
    }

    public function get($url) {
        return $this->makeRequest(Request::get($url));
    }

    public function put($url, $data) {
        $request = Request::put($url)->body(json_encode($data));
        return $this->makeRequest($request);
    }

    public function post($url, $data) {
        $request = Request::post($url)->body(json_encode($data));
        return $this->makeRequest($request);
    }

    public function delete($url) {
        return $this->makeRequest(Request::delete($url));
    }

    public function printResponse($response) {
        $this->logger->info("RESPONSE HEADERS: ".print_r($response->headers, true));
        if (!is_array($response->body->Result) || count($response->body->Result) > 10) {
            $this->logger->info("RESPONSE BODY: ".print_r($response->body, true));
        }
    }

    public function makeRequest($request, $attemptRefresh = true) {

        $response = $request
            ->addHeader('Authorization','Bearer '.$this->tokenManager->access_token)
            ->addHeader('Accept', $this->response_format_header)
            ->addHeader('Content-Type', "application/json")
            ->addHeader('Set-Cookie', "PHPSESSID=".session_id())
            ->send();

        if ($response->code == 403 && $attemptRefresh) {
            $this->tokenManager->getToken();
            $this->logger->info("403 - attempting to refresh token and try request again.");
            return $this->makeRequest($request, false);
        }

        $this->printResponse($response);

        return $response;
    }

    public function getDataPageDetails($appkey)
    {
        $url = sprintf($this->rest_url.'/datapages/'.$appkey);
        $this->logger->debug("REST URL: ".$url);
        $response = $this->get($url);
        $this->printResponse($response);
        return $response;
    }

    public function getViewRowsWhere($view, $where)
    {
        return $this->getAllRows('views', $view, ['where'=>$where]);
    }

    public function getViewRowsByQuery($view, $query)
    {
        return $this->getAllRows('views', $view, $query);
    }

    public function getTableRowsWhere($table, $where)
    {
        return $this->getAllRows('tables', $table, ['where'=>$where]);
    }

    public function getAllTableRows($table, $query = [])
    {
        return $this->getAllRows('tables', $table, $query);
    }

    public function getTableRowsByQuery($table, $query)
    {
        return $this->getAllRows('tables', $table, $query);
    }

    /*
     * Helper func to simplify the recursive paging logic.
     * - we assume that $accumulator will be empty on the first go through, so
     *   if $accumulator is null, we just return $current, which is the current
     *   request response
     */
    protected function mergeResponses($accumulator, $current) {
        if ($accumulator) {
            $accumulator->body->Result = array_merge($accumulator->body->Result, $current->body->Result);
            return $accumulator;
        }
        else {
            return $current;
        }
    }

    /*
     * We have to recur through the pages to get all the entries. Max page size is 1000.
     * We use the first request as the "accumulator" request, merging subsequenty response
     * arrays into the response->body-Result array of the first request. If we receive
     * an error or an unexpected response, we just return that.
     */
    public function getAllRows($type, $name, $query = [], $accumulator = null, $pageNumber = 1) {

        $query['pageSize'] = 1000;
        $query['pageNumber'] = $pageNumber;

        $url = sprintf($this->rest_url.'/'.$type.'/'.$name.'/rows?q=%s', urlencode(json_encode($query)));

        $this->logger->debug("REST URL: ".$url);

        $current = $this->get($url);

        $this->printResponse($current);

        if ($current->code != 200) return $current;

        if (!is_array($current->body->Result)) return $current;

        if (count($current->body->Result) == $query['pageSize']) {
            return $this->getAllRows(
                $type,
                $name,
                $query,
                $this->mergeResponses($accumulator, $current),
                $pageNumber + 1
            );
        }

        return $this->mergeResponses($accumulator, $current);

    }

    public function updateTableRowsWhere($table, $where, $data)
    {
        return $this->updateTableRowsByQuery($table, ['where'=>$where], $data);
    }

    public function updateTableRowsByQuery($table, $query, $data)
    {
        $url = sprintf($this->rest_url.'/tables/'.$table.'/rows?q=%s', urlencode(json_encode($query)));
        $this->logger->debug("REST URL: ".$url);
        $response = $this->put($url, $data);
        $this->printResponse($response);
        return $response;
    }

    public function deleteTableRowsWhere($table, $where)
    {
        return $this->deleteTableRowsByQuery($table, ['where'=>$where]);
    }


    public function deleteTableRowsByQuery($table, $query)
    {
        $url = sprintf($this->rest_url.'/tables/'.$table.'/rows?q=%s', urlencode(json_encode($query)));
        $this->logger->debug("REST URL: ".$url);
        $response = $this->delete($url);
        $this->printResponse($response);
        return $response;
    }

    public function getTableRowsByID($table, $id)
    {
        $url = sprintf($this->rest_url.'/tables/'.$table.'/rows?q=%s', urlencode(json_encode(array('where'=>'PK_ID = '.$id))));
        $this->logger->debug("REST URL: ".$url);
        $response = $this->get($url);
        $this->printResponse($response);
        return $response;
    }

    public function updateTableRowByID($table, $id, $fields)
    {
        $url = sprintf($this->rest_url.'/tables/'.$table.'/rows?q=%s', urlencode(json_encode(array('where'=>'PK_ID = '.$id))));
        $response = $this->put($url, $fields);
        return $response;
    }

    public function deleteTableRowByID($table, $id)
    {
        $url = sprintf($this->rest_url.'/tables/'.$table.'/rows?q=%s', urlencode(json_encode(array('where'=>'PK_ID = '.$id))));
        $response = $this->delete($url);
        return $response;
    }
}