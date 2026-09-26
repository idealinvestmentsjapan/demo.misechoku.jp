<?php

namespace App\Lib;

use Illuminate\Support\Facades\Facade;
use App\Consts\ShopConsts;
use App\Consts\TreatmentConsts;
use App\Repositories\Master\ShopInterface as MyRepository;
use Illuminate\Support\Facades\DB;
use App\Models\Shop;
use App\Models\ShopTreatment;
use App\Models\InformationByShop;
use App\Models\Good;
use App\Models\Good2;
use DateTime;
use Illuminate\Support\Facades\Cookie;
use App\Models\WShop;
use App\Models\WShopSubImage;
use App\Models\WShopIndustry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Like;
use App\Models\Like2;

class ShopInfoUtil extends Facade {

    public static function all () {
        return DB::table('shops')->where('release',\ShopConsts::RELEASE_ON)->get();

    }

    public static function adoptionStatusName ($status) {

        foreach (\RicruitConsts::ADOPTION as $key => $val ) {
            if($status==$val) {
                return $key;
            }
        }
        return "";
    }

    public static function countByBkRecruit($status)
    {
        $count = DB::table('deposits')
                   //->join('shops', 'deposits.shop_id', '=', 'shops.id')
                   //->join('members', 'deposits.member_id', '=', 'members.id')
                   ->where('deposits.status',$status)
                   ->count();

        return $count;

    }

    public static function depositStatusName ($status) {

        foreach (\DepositConsts::DEPOSITS as $key => $val ) {
            if($status==$val) {
                return $key;
            }
        }
        return "";
    }

    public static function approvalStatusName ($approval) {

        foreach (ShopConsts::APPROVAL as $key => $val ) {
            if($approval==$val) {
                return $key;
            }
        }
        return "";
    }
    public static function releaseStatusName ($release) {

        foreach (ShopConsts::RELEASE as $key => $val ) {
            if($release==$val) {
                return $release == 1 ? $key . '中' : $key;
            }
        }
        return "";
    }
    public static function matchingStatusName ($matching) {

        foreach (ShopConsts::MATCHING as $key => $val ) {
            if($matching==$val) {
                return $key;
            }
        }
        return $matching;
    }

    public static function shop_name ($shop_id) {
        //$records = Shop::find($shop_id);
        $records = Shop::where('id',$shop_id)->where('approval',\ShopConsts::APPROVAL_ON)->first();
        if(!Shop::where('id',$shop_id)->where('approval',\ShopConsts::APPROVAL_ON)->exists()) {
            $records = Shop::where('id',$shop_id)->where('approval',\ShopConsts::APPROVAL_OFF2)->first();
        }

        return $records->shop_name;
    }

    public static function isShopDeleted($shop_id)
    {
        $shop = Shop::where('id', $shop_id)->first();

        if (!$shop) {
            return false;
        }

        return $shop->withdrawal == \ShopConsts::WITHDRAWAL_DONE;
    }

    public static function shopData ($shop_id) {

        if(Shop::where('id',$shop_id)->where('approval',\ShopConsts::APPROVAL_ON)->exists()) {
            $records = Shop::where('id',$shop_id)->where('approval',\ShopConsts::APPROVAL_ON)->first();
        }else {
            //if(WShop::where('id',$shop_id)->exists()) {
            //     $records = WShop::where('id',$shop_id)->first();
            //}else{
                $records = Shop::where('id',$shop_id)->first();
            //}
        }
        return $records;
    }

    public static function shopData2 ($shop_id) {

        $records = Shop::where('id',$shop_id)->first();
        return $records;
    }

    public static function matchingCnt ($shop_id) {

        return DB::table('matchings')->where('shop_id',$shop_id)->count();

    }

    public static function isMatching ($shop_id,$member_id) {

        $result =  DB::table('matchings')->where('shop_id',$shop_id)->where('member_id',$member_id)->where('matching_status',\MatchingConsts::MATCHING_OK)->count();

        if($result>0) {
            return true;
        }
        return false;
    }

    public static function postedCnt ($shop_id) {

        return DB::table('posteds')->where('shop_id',$shop_id)->count();

    }

    public static function reviewdCnt ($shop_id) {

        return DB::table('reviews')->where('shop_id',$shop_id)->count();

    }


    public static function reviewdNgCnt ($shop_id) {

        return DB::table('reviews')->where('result',\ReviewConsts::APPROVAL_NG)->where('shop_id',$shop_id)->count();

    }

    public static function reviewdOkCnt ($shop_id) {

        return DB::table('reviews')->where('result',\ReviewConsts::APPROVAL_OK)->where('shop_id',$shop_id)->count();

    }

    public static function reviewdYetCnt ($shop_id) {

        return DB::table('reviews')->where('result',\ReviewConsts::APPROVAL_YET)->where('shop_id',$shop_id)->count();

    }

    public static function reviewdNg ($shop_id) {

        return DB::table('reviews')->where('result',\ReviewConsts::APPROVAL_NG)->where('shop_id',$shop_id)->get();

    }

    public static function reviewdOk ($shop_id) {

        return DB::table('reviews')->where('result',\ReviewConsts::APPROVAL_OK)->where('shop_id',$shop_id)->get();

    }

    public static function reviewdYet ($shop_id) {

        return DB::table('reviews')->where('result',\ReviewConsts::APPROVAL_YET)->where('shop_id',$shop_id)->get();

    }

    public static function reviewdReadedOnCnt ($shop_id) {

        return DB::table('reviews')->where('readed',\ShopConsts::STATUS_ON)->where('shop_id',$shop_id)->count();

    }

    public static function reviewdReadedOffCnt ($shop_id) {

        return DB::table('reviews')->where('readed',\ShopConsts::STATUS_OFF)->where('shop_id',$shop_id)->count();

    }

    public static function getNotYetReadedMessage ($shop_id) {
        if (empty($shop_id)) {
            return 0;
        }
        $shop_id = \StrUtil::dec($shop_id);


        return DB::table('messages')
            ->join('shops', 'messages.shop_id', '=', 'shops.id')
            ->join('members', 'messages.member_id', '=', 'members.id')
//            ->where('members.approval', \FrontConsts::APPROVAL_ON)
//            ->where('members.del_flg', \CommonConsts::DEL_OFF)
            ->where('messages.shop_id', $shop_id)
            ->where('messages.readed',\CommonConsts::READ_YET)
            ->where('messages.type',2)
            ->count();

    }
    public static function followCnt ($shop_id) {

        return DB::table('follows')->where('shop_id',$shop_id)->count();

    }

    public static function shopUpdateCnt ($shop_id) {

//        return DB::table('shop_updates')->where('shop_id',$shop_id)->count();
        return DB::table('information_by_shops')->where('shop_id',$shop_id)->where('release',\ShopConsts::RELEASE_ON)->groupBy('seq')->count();

    }

    public static function shopUpdateCnt2 ($shop_id) {

//        return DB::table('shop_updates')->where('shop_id',$shop_id)->count();
        return DB::table('information_by_shops')->where('shop_id',$shop_id)->where('release',\ShopConsts::RELEASE_ON)->count();

    }

    public static function adoptionsnFullCnt ($shop_id) {

        return DB::table('applies')
            ->where('shop_id', $shop_id)
            ->where('status', \RicruitConsts::INTERVIEW_DONE)
            ->where('adoption', \RicruitConsts::ADOPTION_OK)
            ->where('about_recruit', \RicruitConsts::ABOUT_RECRUIT_FULLTIME)
            ->count();

    }

    public static function adoptionsnTrialCnt ($shop_id) {

        return DB::table('applies')
            ->where('shop_id', $shop_id)
            ->where('status', \RicruitConsts::INTERVIEW_DONE)
            ->where('adoption', \RicruitConsts::ADOPTION_OK)
            ->where('about_recruit', \RicruitConsts::ABOUT_RECRUIT_TRIAL)
            ->count();

    }

    public static function adoptionsnHelpCnt ($shop_id) {

        return DB::table('applies')
            ->where('shop_id', $shop_id)
            ->where('status', \RicruitConsts::INTERVIEW_DONE)
            ->where('adoption', \RicruitConsts::ADOPTION_OK)
            ->where('about_recruit', \RicruitConsts::ABOUT_RECRUIT_HELP)
            ->count();

    }

    public static function adoptionsnNgCnt ($shop_id) {

        return DB::table('applies')
            ->where('shop_id', $shop_id)
            ->where('status', \RicruitConsts::INTERVIEW_DONE)
            ->where('adoption', \RicruitConsts::ADOPTION_REJECTED)
            ->count();

    }

    public static function withinPrefShopCnt ($pref) {

//        return DB::table('shops')->where('shops.release',\ShopConsts::RELEASE_ON)->where('pref',$pref)->count();

        return   DB::table('shops')
                  ->join('jobs', 'shops.id', '=', 'jobs.shop_id')
//                  ->where('shops.release',\ShopConsts::RELEASE_ON)
//                  ->where('shops.approval',\ShopConsts::APPROVAL_ON)
                  ->where('pref',$pref)
                  ->count();


    }

    public static function withinCityShop ($pref) {

        return DB::table('shops')
            ->join('jobs', 'shops.id', '=', 'jobs.shop_id')
//            ->where('shops.release',\ShopConsts::RELEASE_ON)
            ->where('pref',$pref)
            ->groupBy('city')
            ->select('city')
            ->get();

    }

    public static function withinCityShopCnt ($pref,$city) {

        return DB::table('shops')
            ->join('jobs', 'shops.id', '=', 'jobs.shop_id')
//            ->where('shops.release',\ShopConsts::RELEASE_ON)
            ->where('pref',$pref)
            ->where('city',$city)
            ->count();

    }


    public static function adoptionsnFull ($shop_id) {

        $records = DB::table('adoptions')
                   ->join('shops', 'adoptions.shop_id', '=', 'shops.id')
                   ->where('adoptions.shop_id',$shop_id)
                   ->where('result',\RicruitConsts::ADOPTION_FULL)
                   ->select('member.*')
                   ->get();
        return  $records;

    }

    public static function adoptionsnHelp ($shop_id) {

        $records = DB::table('adoptions')
                   ->join('shops', 'adoptions.shop_id', '=', 'shops.id')
                   ->where('adoptions.shop_id',$shop_id)
                   ->where('result',\RicruitConsts::ADOPTION_HELP)
                   ->select('member.*')
                   ->get();
        return  $records;

    }

    public static function adoptionsnNg ($shop_id) {

        $records = DB::table('adoptions')
                   ->join('shops', 'adoptions.shop_id', '=', 'shops.id')
                   ->where('adoptions.shop_id',$shop_id)
                   ->where('result',\ShopConsts::ADOPTION_NG)
                   ->select('member.*')
                   ->get();
        return  $records;

    }

    public static function last_shop_update_at ($shop_id) {

        $records = DB::table('information_by_shops')->where('shop_id',$shop_id)->where('release',\ShopConsts::RELEASE_ON)->orderBy('updated_at','desc')->get();
        $updated_at = "";
        foreach($records as $val ) {
             $updated_at = $val->updated_at;
             break;
        }
        //return $last_login_at;
        if($updated_at=="") return "未更新";
        else return \StrUtil::y_m_d_h_i($updated_at);

    }

    public static function last_login_at ($shop_id) {

        $records = DB::table('shops')
                   ->join('shop_managers', 'shops.id', '=', 'shop_managers.shop_id')
                   ->where('shops.id',$shop_id)
                   ->orderBy('shop_managers.last_login_at','desc')
                   ->limit(1)
                   ->select('shop_managers.last_login_at')
                   ->get();
        $last_login_at = "";
        foreach($records as $val ) {
             $last_login_at = $val->last_login_at;
             break;
        }
        //return $last_login_at;
        if($last_login_at=="") return "未ログイン";
        else return \StrUtil::y_m_d_h_i($last_login_at);
    }

    public static function isAdmin ($shop_id,$email) {
         if(Shop::where('id',$shop_id)->where('email',$email)->exists()){
              return true;
         }
         return false;
    }

    public static function salaryCnt($shop_id)
    {
        $cond_array = array_values(\TreatmentConsts::SALARY);
        $records =  ShopTreatment::where('shop_id',$shop_id)->whereIn('treatment',$cond_array)->count();

        return $records;
    }

    public static function salary($shop_id)
    {

        $records =  DB::table('tags')->
                    join("shop_treatments","shop_treatments.treatment","=","tags.id")->
                    where('shop_id',$shop_id)->
                    where('tags.type',\TreatmentConsts::VARIABLE_1)->
                    select('tags.content')->get();

        return $records;

    }

    public static function howtoCnt($shop_id)
    {
        $cond_array = array_values(\TreatmentConsts::HOWTO);
        $records =  ShopTreatment::where('shop_id',$shop_id)->whereIn('treatment',$cond_array)->count();

        return $records;
    }

    public static function howto($shop_id)
    {
        $records =  DB::table('tags')->
                    join("shop_treatments","shop_treatments.treatment","=","tags.id")->
                    where('shop_id',$shop_id)->
                    where('tags.type',\TreatmentConsts::VARIABLE_2)->
                    select('tags.content')->get();

        return $records;
    }

    public static function atmosphereCnt($shop_id)
    {
        $cond_array = array_values(\TreatmentConsts::VARIABLE_8);
        $records =  ShopTreatment::where('shop_id',$shop_id)->whereIn('treatment',$cond_array)->count();

        return $records;
    }

    public static function atmosphere($shop_id)
    {
        $records =  DB::table('tags')->
                    join("shop_treatments","shop_treatments.treatment","=","tags.id")->
                    where('shop_id',$shop_id)->
                    where('tags.type',\TreatmentConsts::VARIABLE_8)->
                    select('tags.content')->get();

        return $records;
    }

    public static function isTreatmentChecked($treatmens,$master) {

        foreach($treatmens as $key => $val) {
            if($val->treatment==$master) {
                     return true;
            }
        }
        return false;

    }

    public static function meritCnt($shop_id)
    {
        $cond_array = array_values(\TreatmentConsts::MERIT);
        $records =  ShopTreatment::where('shop_id',$shop_id)->whereIn('treatment',$cond_array)->count();

        return $records;
    }

    public static function merit($shop_id)
    {
        $records =  DB::table('tags')->
                    join("shop_treatments","shop_treatments.treatment","=","tags.id")->
                    where('shop_id',$shop_id)->
                    where('tags.type',\TreatmentConsts::VARIABLE_3)->
                    select('tags.content')->get();

        return $records;
    }

    public static function featureCnt($shop_id)
    {
        $cond_array = array_values(\TreatmentConsts::FEATURE);
        $records =  ShopTreatment::where('shop_id',$shop_id)->whereIn('treatment',$cond_array)->count();

        return $records;
    }

    public static function feature($shop_id)
    {
        $records =  DB::table('tags')->
                    join("shop_treatments","shop_treatments.treatment","=","tags.id")->
                    where('shop_id',$shop_id)->
                    where('tags.type',\TreatmentConsts::VARIABLE_4)->
                    select('tags.content')->get();
        return $records;
    }

    public static function facilityCnt($shop_id)
    {
        $cond_array = array_values(\TreatmentConsts::FACILITY);
        $records =  ShopTreatment::where('shop_id',$shop_id)->whereIn('treatment',$cond_array)->count();


        return $records;
    }

    public static function facility($shop_id)
    {
        $records =  DB::table('tags')->
                    join("shop_treatments","shop_treatments.treatment","=","tags.id")->
                    where('shop_id',$shop_id)->
                    where('tags.type',\TreatmentConsts::VARIABLE_5)->
                    select('tags.content')->get();

        return $records;
    }







    // 報酬
    public static function findSalary()
    {
        return  DB::table('tags')->where('type',\TreatmentConsts::VARIABLE_1)->get();
    }

    // 働き方
    public static function findHowTo()
    {
        return  DB::table('tags')->where('type',\TreatmentConsts::VARIABLE_2)->get();
    }


    // メリット
    public static function findMerit()
    {
        return  DB::table('tags')->where('type',\TreatmentConsts::VARIABLE_3)->get();
    }

    // 特徴
    public static function findFeacture()
    {
        return  DB::table('tags')->where('type',\TreatmentConsts::VARIABLE_4)->get();
    }

    // 設備
    public static function findFacility()
    {
        return  DB::table('tags')->where('type',\TreatmentConsts::VARIABLE_5)->get();
    }

    public static function findAtmosphere()
    {
        return  DB::table('tags')->where('type',\TreatmentConsts::VARIABLE_8)->get();
    }

    public static function isImgFile ($enc_shop_id) {
        $shop_id = \StrUtil::dec($enc_shop_id);
        $res = false;

        if(!empty(Shop::find($shop_id)->shop_main_img)) {
            return true;
        }
        return false;

    }

    public static function shop_license_img ($enc_shop_id) {
        $shop_id = \StrUtil::dec($enc_shop_id);
        $res = false;

        if(!empty(Shop::find($shop_id)->shop_license_img)) {
            return true;
        }
        return false;

    }

    public static function shop_license2_img ($enc_shop_id) {
        $shop_id = \StrUtil::dec($enc_shop_id);
        $res = false;

        if(!empty(Shop::find($shop_id)->shop_license2_img)) {
            return true;
        }
        return false;

    }
    public static function isNew ($enc_shop_id) {
        $shop_id = \StrUtil::dec($enc_shop_id);
        $res = Shop::find($shop_id);
        if($res === null) return false;
        if($res->updated_at === null) return false;

        date_default_timezone_set('Asia/Tokyo');
        $date1 = new DateTime('now');
        $date2 = new DateTime($res->updated_at);
        $date3 = $date1->diff($date2);

        if($date3->days < \CommonConsts::NEW) return true;
        return false;

    }


    public static function isNew2 ($updated_at) {

        date_default_timezone_set('Asia/Tokyo');
        $date1 = new DateTime('now');
        $date2 = new DateTime($updated_at);
        $date3 = $date1->diff($date2);
        if($date3->days < \CommonConsts::NEW) return true;
        return false;

    }

    public static function newImg ($shop_id) {

        if(self::isnew(\StrUtil::enc($shop_id))){
           return "<p class='new'><img src='/front/control/img/icon-new.svg' alt=''></p>";
        }else{
           return "";
        }


    }


    public static function calDistance ($request) {

        $latitude  = $request->get('latitude');

        $longitude = $request->get('longitude');
        $shops = Shop::select('*',
          DB::raw('6370 * ACOS(COS(RADIANS('.$latitude.')) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS('.$longitude.'))
            + SIN(RADIANS('.$latitude.')) * SIN(RADIANS(latitude))) as distance'))
          ->orderBy('distance')
          ->get();
        return $shops;
    }

    public static function evaluationCnt ($shop_id) {

        return DB::table('reviews')->where('shop_id',$shop_id)->count();

    }

    public static function evaluationAvg ($shop_id) {

        return DB::table('reviews')
                   ->join('review_details', 'reviews.id', '=', 'review_details.review_id')
                   ->where('reviews.shop_id',$shop_id)
                   ->avg('review_details.score');



    }

    public static function evaluationAvg2 ($id) {

        return DB::table('reviews')
                   ->join('review_details', 'reviews.id', '=', 'review_details.review_id')
                   ->where('reviews.id',$id)
                   ->avg('review_details.score');



    }


    public static function getEvaluation ($shop_id) {

        $res =  DB::table('evaluations')->where('shop_id',$shop_id)->get();

        if(!empty($res[0])) return $res[0]->eva;
        else return 0;
    }

    public static function getEvaluationReview ($id) {

        $res =  DB::table('reviews')->where('id',$id)->get();

        if(!empty($res[0])) return $res[0]->eva;
        else return 0;
    }


    public static function getAllInformationBySeq($seq)
    {
        $records =  InformationByShop::where('seq',$seq)->get();

        return $records;
    }

    public static function getUnleadInformationBySeq($seq)
    {
        $records =  InformationByShop::where('seq',$seq)->where('status',\ShopConsts::STATUS_OFF)->count();

        return $records;
    }



    public static function goodCnt($shop_id)
    {
        $records =  Good::where('shop_id',$shop_id)->count();
        return $records;
    }

    public static function goodCntSeq($seq)
    {
        $records =  Good::where('seq',$seq)->count();
        return $records;
    }

    public static function goodByMemberCnt($seq,$member_id)
    {
        $records =  Good::where('seq',$seq)->where('member_id',$member_id)->count();
        return $records;
    }

    public static function goodByMemberCnt2($shop_id,$member_id)
    {
        $records =  Good::whereNull('seq')->where('shop_id',$shop_id)->where('member_id',$member_id)->count();
        return $records;
    }

    public static function likeByMemberCnt2($shop_id,$member_id)
    {
        $records =  Like::where('shop_id',$shop_id)->where('member_id',$member_id)->count();
        return $records;
    }

    public static function goodByShopCnt2($shop_id,$member_id)
    {
        $records =  Good2::where('shop_id',$shop_id)->where('member_id',$member_id)->count();
        return $records;
    }

    public static function likeByShopCnt2($shop_id,$member_id)
    {
        $records =  Like2::where('shop_id',$shop_id)->where('member_id',$member_id)->count();
        return $records;
    }

    public static function good2Cnt($shop_id)
    {
        $records =  Good2::where('shop_id',$shop_id)->count();
        return $records;
    }

    public static function isLikeDailyByShop($shop_id,$member_id)
    {
        $today = Carbon::today();

        $existingLike  =  Like2::where('shop_id',$shop_id)->where('member_id',$member_id)->where('created_at', '>=', $today)->first();

        if ($existingLike) {
            return true;
        }
        return false;
    }


    public static function likeByMemberCnt($shop_id,$member_id)
    {
        $records =  Like::where('shop_id',$shop_id)->where('member_id',$member_id)->count();
        return $records;
    }


    public static function likeCnt($shop_id)
    {
        $records =  Like::where('shop_id',$shop_id)->count();
        return $records;
    }

    public static function isGoodDaily($shop_id,$member_id)
    {
        $today = Carbon::today();

        $existingLike  =  Good::where('shop_id',$shop_id)->where('member_id',$member_id)->where('created_at', '>=', $today)->first();

        if ($existingLike) {
            return true;
        }
        return false;
    }


    public static function isLikeDaily($shop_id,$member_id)
    {
        $today = Carbon::today();

        $existingLike  =  Like::where('shop_id',$shop_id)->where('member_id',$member_id)->where('created_at', '>=', $today)->first();

        if ($existingLike) {
            return true;
        }
        return false;
    }

    public static function distance($shop_lat, $shop_lng)
    {
        Log::info("Calculated Distance: start ");

        // 店舗の緯度・経度が未設定の場合はスキップ
        if (empty($shop_lat) || empty($shop_lng)) {
            Log::warning("Calculated Distance: shop has no lat/lng");
            return null;
        }

        // ユーザーの緯度・経度Cookieが存在しない場合はスキップ
        if (!isset($_COOKIE['lat']) || !isset($_COOKIE['lng'])) {
            Log::warning("Calculated Distance: user has no lat/lng cookie");
            return null;
        }

        // ユーザーの現在地（ラジアンに変換）
        $start_latitude = deg2rad((float)$_COOKIE['lat']);
        $start_longitude = deg2rad((float)$_COOKIE['lng']);

        Log::info("User Position (rad) → Lat: {$start_latitude}, Lng: {$start_longitude}");

        // 店舗の位置（ラジアンに変換）
        $end_latitude  = deg2rad((float)$shop_lat);
        $end_longitude = deg2rad((float)$shop_lng);

        // 地球の半径（km）
        $earth_r = 6378.137;

        // ハーバーサイン公式による距離計算
        $latitude_margin = $end_latitude - $start_latitude;
        $longitude_margin = $end_longitude - $start_longitude;

        $a = pow(sin($latitude_margin / 2), 2) +
            cos($start_latitude) * cos($end_latitude) * pow(sin($longitude_margin / 2), 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // 距離（km）
        $distance = $earth_r * $c;

        // 小数点以下2桁まで丸める
        $distance = round($distance, 2);

        Log::info("Calculated Distance: " . $distance . " km");

        return $distance;
    }


    public static function star($eva)
    {
        $html = "";
        $eva = (float)$eva;

        if($eva>=0.0&&0.5>$eva){
            $html = "<i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>";

        }else if($eva>=0.5&&1.0>$eva){
            $html = "<i class=\"star_icon half\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>";

        }else if($eva>=1.0&&1.5>$eva){
            $html = "<i class=\"star_icon full\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>";

        }else if($eva>=1.5&&2.0>$eva){
            $html = "<i class=\"star_icon full\"></i>
            <i class=\"star_icon half\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>";

        }else if($eva>=2.0&&2.5>$eva){
            $html = "<i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>";

        }else if($eva>=2.5&&3.0>$eva){
            $html = "<i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon half\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>";

        }else if($eva>=3.0&&3.5>$eva){
            $html = "<i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon none\"></i>
            <i class=\"star_icon none\"></i>";

        }else if($eva>=3.5&&4.0>$eva){
            $html = "<i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon half\"></i>
            <i class=\"star_icon none\"></i>";

        }else if($eva>=4.0&&4.5>$eva){
            $html = "<i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon none\"></i>";

        }else if($eva>=4.5&&5.0>$eva){
            $html = "<i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon half\"></i>";

        }else{
            $html = "<i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>
            <i class=\"star_icon full\"></i>";
        }

        return $html;
    }

    public static function getAllForShopCnt($shop_id)
    {
/*
        return  DB::table('news')->where('type','<>',\NewsConsts::Type_2)->where('del_flg',\CommonConsts::DEL_OFF)->
                where('updated_at','>=','DATEADD(day,-7,GETDATE())')->count();
*/
        return DB::table('readed_shop_news')->where('shop_id',$shop_id)->where('is_readed','0')->count();

    }

    public static function isReadedNews($news_id,$shop_id)
    {

        $cnt = DB::table('readed_shop_news')->where('news_id',$news_id)->where('shop_id',$shop_id)->where('is_readed','1')->count();

        if($cnt>0) return true;
        return false;
    }

    public static function isGoodByMyselfDaily($shop_id)
    {
        $today = Carbon::today();

        $existingLike  =  Good::where('shop_id', $shop_id)->where('created_at', '>=', $today)->first();

        if ($existingLike) {
            return true;
        }
        return false;
    }

    public static function blockByMemberCnt($shop_id, $member_id)
    {
        return DB::table('follows')
            ->where('shop_id', $shop_id)
            ->where('member_id', $member_id)
            ->where('blocked_by', 'member')
            ->where('type', \CommonConsts::FORROW_BY_MEMBER_NG)
            ->count();
    }

    public static function blockByShopCnt($shop_id, $member_id)
    {
        return DB::table('follows')
            ->where('shop_id', $shop_id)
            ->where('member_id', $member_id)
            ->where('blocked_by', 'shop')
            ->where('type', \CommonConsts::FORROW_BY_SHOP_NG)
            ->count();
    }


    public static function fixShops(): string
    {
        $shops = DB::table('shops')->get();

        if ($shops->isEmpty()) {
            Log::info("⚠️ No shop found");
            return "⚠️ No shop found";
        }

        // Pref id → name
        $prefMap = [
            null, '北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県',
            '茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県',
            '新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県',
            '静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県',
            '奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県',
            '徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県',
            '熊本県','大分県','宮崎県','鹿児島県','沖縄県'
        ];

        $updatedCount = 0;

        foreach ($shops as $shop) {
            Log::info("⏳ Checking shop_id={$shop->id}, zipcode={$shop->zip}");

            if (empty($shop->zip)) {
                Log::warning("➡️ Skip shop_id={$shop->id} (no zipcode)");
                continue;
            }

            $zip = str_replace('-', '', $shop->zip);
            if (strlen($zip) < 7) {
                Log::warning("⚠️ Invalid zip={$zip} for shop_id={$shop->id}");
                continue;
            }

            $prefix = substr($zip, 0, 3);
            $url = "https://yubinbango.github.io/yubinbango-data/data/{$prefix}.js";

            try {
                $res = Http::timeout(10)->get($url);
            } catch (\Exception $e) {
                Log::error("❌ Failed to fetch {$url}: {$e->getMessage()}");
                continue;
            }

            if (!$res->successful()) {
                Log::error("❌ Failed to fetch {$url}");
                continue;
            }

            // JSONP → JSON
            $body = trim($res->body());
            if (str_starts_with($body, '$yubin(')) {
                $body = substr($body, 7, -2);
            }
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("❌ JSON parse error for {$url}");
                continue;
            }

            if (!isset($data[$zip])) {
                Log::warning("⚠️ Zip {$zip} not found in {$url}");
                continue;
            }

            $addr   = $data[$zip];
            $prefId = $addr[0] ?? null;
            $pref   = $prefId ? ($prefMap[$prefId] ?? '') : '';
            $city   = $addr[1] ?? '';
            $area   = $addr[2] ?? '';
            $strt   = $addr[3] ?? '';

            Log::info("📌 Found address: {$pref} {$city} {$area} {$strt}");

            [$lat, $lng] = self::latLng($pref, $city, $shop->addr2 ?? '', $shop->addr3 ?? '');
            Log::info("🌍 lat={$lat}, lng={$lng}");

            // Update shops
            DB::table('shops')->where('id', $shop->id)->update([
                'pref'      => $pref,
                'city'      => $city,
                'latitude'  => $lat,
                'longitude' => $lng,
            ]);

            // Update w_shops
            DB::table('w_shops')->where('id', $shop->id)->update([
                'pref'      => $pref,
                'city'      => $city,
                'latitude'  => $lat,
                'longitude' => $lng,
            ]);

            $updatedCount++;
            Log::info("✅ Updated shop_id={$shop->id} in shops & w_shops");
        }

        Log::info("🎯 Total updated: {$updatedCount} shops");
        return "✅ Done! Total updated: {$updatedCount} (Update Pref, City, Latitude, Longitude From zipCode)";
    }

}


