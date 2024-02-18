<?php

namespace App\Http\Controllers;
use App\Http\Middleware\HttpsProtocol;
use App\Models\DatasourceUrl;
use App\Models\RedirectLogs;
use App\Models\RedirectStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;
use Exception;

class RedirectController extends Controller
{
    public function redirect($codigo, Request $request)
    {
        $redirectLogs = new RedirectLogs();
        $params = "";
        if ($request->query('params')) {
            $params = $request->query('params');
            $params = explode('?', $params);
            $params = $params[1];
        }
        $redirectLogs->setRequestIp($request->getClientIp());
        $redirectLogs->setUser($request->getUser());
        $redirectLogs->setParams($params);
        $redirectLogs->setReferer($request->headers->get('referer'));
        $redirectLogs->setAccess(date('Y-m-d H:i:s') );
        $redirectLogs->setAccess(date('Y-m-d H:i:s') );

        $datasources = DatasourceUrl::find(Hashids::decode($codigo)[0]);

        $redirectLogs->setIdUrl($datasources->getId());
        $redirectLogs->save();

        $redirectStats = new RedirectStats();

        $datasource = RedirectStats::query()->where(['redirect_stats.url_id' => $redirectLogs->getIdUrl()])->first();
        if($datasource) {
            RedirectStats::query()->where(['redirect_stats.url_id' => $redirectLogs->getIdUrl()])
                ->update([
                    'acess_total' => $datasource->getAttribute('acess_total') +1,
                    'top_referrers' => $redirectLogs->getIdUrl(),
                ]);
            return redirect($datasources->getUrl());
        }
        $redirectStats->setIdUrl($datasources->getId());

        $redirectStats->setUniqueIp($redirectLogs->getAttribute('request_ip'));
        $redirectStats->setTopReferrers($datasources->getId());
        $redirectStats->setTotalAccess(1);

        $redirectStats->save();



        if(response($datasources->getUrl())->getStatusCode() != '200' || !$this->checkHost($datasources->getUrl(), $request) || !$this->checkHttps($datasources->getUrl()) ) {
            throw new Exception('Ocorreu um erro!');
        }
        return redirect($datasources->getUrl());
    }
    public function stats($codigo, Request $request, DatasourceUrl $datasourceUrl, RedirectLogs $redirectLogs) {
        $redirectStats = RedirectStats::query()->where(['redirect_stats.url_id' => Hashids::decode($codigo)[0]])->first();
dd($redirectStats);
        return view('api.redirects', [
            'stats' => $redirectStats
        ]);
    }


    public function checkHttps($url) {
        $url = parse_url($url, PHP_URL_SCHEME);
        if ($url == 'https') {
            return true;
        }
        return false;
    }

    public function checkHost($url, $request) {
        $host = $request->getHost();
        if ($host != $url) {
            return true;
        }
        return false;
    }
}
