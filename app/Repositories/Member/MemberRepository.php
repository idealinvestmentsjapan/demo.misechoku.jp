<?php

namespace App\Repositories\Member;

use App\Models\Cast;
use App\Models\WMember;
use App\Models\MemberIndustry;
use App\Models\MemberIndustryExp;
use App\Consts\FrontConsts;
use App\Consts\ShopConsts;
use App\Consts\CommonConsts;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Lib\StrUtil;
use App\Models\WMemberImage;
use App\Models\MemberImage;
use App\Models\Apply;
use App\Models\Good2;
use App\Models\Like;
use App\Models\Like2;
use App\Lib\FileUtil;
use App\Models\BankAccount;
use App\Models\TodayCentensByMember;
use App\Models\MemberTag;
use App\Models\Matching;
use App\Models\Follow;
use App\Models\Report;

class MemberRepository implements MemberRepositoryInterface
{
    /**
     * @var App\Models\Project
     */
    private $project;

    public function __construct(Cast $project)
    {
        $this->project = $project;
    }

    /**
     *
     * @return Collection
     */
    public function getAll()
    {
        return  DB::table('members')->whereIn('del_flg', [\CommonConsts::DEL_OFF, \CommonConsts::DEL_ON])->orderBy('updated_at', 'asc')->paginate(\ShopConsts::PAGENATION_COUNT);
    }

    public function beroreApprova()
    {
        return  DB::table('members')->where('del_flg', \CommonConsts::DEL_OFF)->where('approval', '<=', \ShopConsts::APPROVAL_OFF2)->orderBy('updated_at', 'asc')->paginate(\ShopConsts::PAGENATION_COUNT);
    }

    public function newApprovaCnt()
    {
        return Cast::where('status', \FrontConsts::APPROVAL_OFF)->count();
    }

    public function getActiveUser()
    {
        return  DB::table('members')->where('members.approval', \FrontConsts::APPROVAL_ON)->where('members.del_flg', \CommonConsts::DEL_OFF)->orderBy('updated_at', 'asc')->paginate(\ShopConsts::PAGENATION_COUNT);
    }



    /**
     *
     * @param array $data
     * @return Project
     */
    public function store(Request $request, $member_id)
    {

        $res = $this->saveMember($request, $member_id);
        if (!$res) return false;
        $res = $this->saveMemberIdentity($request, $member_id);
        if (!$res) return false;
        $res = $this->saveMemberIdentityExp($request, $member_id);
        if (!$res) return false;
        $res = $this->saveMemberTag($request, $member_id);
        if (!$res) return false;

        return true;
    }


    public function storeFromFront(Request $request, $member_id)
    {

        $res = $this->saveMember($request, $member_id);
        if (!$res) return false;
        $res = $this->saveMemberIdentity($request, $member_id);
        if (!$res) return false;
        $res = $this->saveMemberIdentityExp($request, $member_id);
        if (!$res) return false;

        return true;
    }

    public function saveMemberFromFront($inputData, $member_id)
    {
        $inputData['status'] = FrontConsts::APPROVAL_OFF;
        $member = $this->syncCastRecord($member_id, $inputData);
        $res = (bool) $member;
        if (!$res) return false;
        $industrys = $inputData['industry'];
        foreach ($industrys as $key => $industry) {
            try {
                MemberIndustry::create(
                    array('member_id' => $member_id, 'industry_id' => $key + 1)
                );
            } catch (Exception $e) {
                return false;
            }
        }
        return true;
    }

    public function saveMember(Request $request, $member_id)
    {
        $payload = $request->except('_token', '_method');
        $payload['status'] = FrontConsts::APPROVAL_ON;

        return (bool) $this->syncCastRecord($member_id, $payload);
    }

    public function saveMemberIdentity(Request $request, $member_id)
    {

        MemberIndustry::where('member_id', $member_id)->delete();

        $industrys = $request->all()['industry'];

        foreach ($industrys as $key => $industry) {
            try {
                MemberIndustry::create(
                    array('member_id' => $member_id, 'industry_id' => $industry)
                );
            } catch (Exception $e) {
                return false;
            }
        }
        return true;
    }

    public function saveMemberIdentityExp(Request $request, $member_id)
    {

        MemberIndustryExp::where('member_id', $member_id)->delete();

       $data = $request->all();

       if (isset($data['industry_exp'])&&$data['exp']=="1") {

            $industrys = $request->all()['industry_exp'];
            foreach ($industrys as $key => $industry) {
                try {
                    MemberIndustryExp::create(
                        array('member_id' => $member_id, 'industry_id' => $industry)
                    );
                } catch (Exception $e) {
                    return false;
                }
            }
        }
        return true;
    }

    public function saveMemberTag(Request $request, $member_id)
    {

        MemberTag::where('member_id', $member_id)->delete();

        //$cast_tags = $request->all()['cast_tag'];
        $cast_tags = $request->has('cast_tag') ? $request->input('cast_tag') : [];

        foreach ($cast_tags as $key => $cast_tag) {
            try {
                MemberTag::create(
                    array('member_id' => $member_id, 'tag_id' => $cast_tag)
                );
            } catch (Exception $e) {
                return false;
            }
        }
        return true;
    }


    public function store2(Request $request, $member_id)
    {

        $res = $this->saveMember($request, $member_id);
        if (!$res) return false;
        //$this->saveMemberIdentity($request,$member_id);
        $this->saveSubImage($request, $member_id);
        $this->saveCertificate($request, $member_id);

        return $res;
    }


    public function findCertById($member_id)
    {

        $records = DB::select(
            "SELECT * FROM member_images where member_id =? and type in (" . \FrontConsts::IDENTITY_1 . "," . \FrontConsts::IDENTITY_2 . " ," . \FrontConsts::IDENTITY_3 . ") " .
                "order by updated_at desc limit 1",
            [$member_id]
        );
        return $records;
    }

    public function findCertById2($member_id)
    {

        $records = DB::table('member_images')->whereIn('type', [\FrontConsts::IDENTITY_1, \FrontConsts::IDENTITY_2, \FrontConsts::IDENTITY_3])->where('member_id', $member_id)->orderBy('updated_at', 'desc')->first();

        return $records;
    }

    public function saveCertificate(Request $request, $member_id)
    {

        $f = new FileUtil();
        $type = $request->all()['type'];;
        $form_data = $request->all();

        foreach (\FrontConsts::IDENTITY as $key => $val) {
            WMemberImage::where('member_id', $member_id)->where('type', $val)->delete();
            MemberImage::where('member_id', $member_id)->where('type', $val)->delete();
        }

        $data = [];
        $front_and_back = "";
        foreach (FrontConsts::CERT_FILE_NAMES as $index => $name) {

            //$img="";
            if ($name == \FrontConsts::FRONT) {
                $front_and_back = \FrontConsts::FRONT_IMG;
            } else if ($name == \FrontConsts::BACK) {
                $front_and_back = \FrontConsts::BACK_IMG;
            }
            if (!empty($_FILES[$name]['tmp_name'])) {

                $f->uploade($request, $name, FrontConsts::MEMBER_MAIN_IMG_DIR . $member_id . '/');
                $img =  $f->getOriginalFileName($request, $name);
                $data = ['img' => $img, 'type' => $type, 'front_and_back' => $front_and_back];
            } else {
                if ($name == \FrontConsts::FRONT) {
                    $front_and_back = \FrontConsts::FRONT_IMG;
                    $front_and_back_img = \FrontConsts::FRONT;
                } else if ($name == \FrontConsts::BACK) {
                    $front_and_back = \FrontConsts::BACK_IMG;
                    $front_and_back_img = \FrontConsts::BACK;
                }
                $img = (isset($form_data["hidd_" . $front_and_back_img]) && !empty($form_data["hidd_" . $front_and_back_img]))
                        ? $form_data["hidd_" . $front_and_back_img]
                        : $form_data[$front_and_back_img];
                $data = ['img' => $img, 'type' => $type, 'front_and_back' => $front_and_back];
            }


            WMemberImage::updateOrCreate(
                ['member_id' => $member_id, 'type' => $type, 'front_and_back' => $front_and_back],
                $data
            );

            MemberImage::updateOrCreate(
                ['member_id' => $member_id, 'type' => $type, 'front_and_back' => $front_and_back],
                $data
            );
        }
    }

    //

    public function saveSubImage(Request $request, $member_id)
    {
        $f = new FileUtil();

        $imageOrder = $request->input('image_order') ? explode(',', $request->input('image_order')) : range(0, \ShopConsts::SHOP_SUB_IMAGE_FILE_CNT - 1);

        // �Â��摜���폜
        $type = \FrontConsts::IDENTITY_5;
        MemberImage::where('member_id', $member_id)->where('type', $type)->delete();

        $type = \FrontConsts::IDENTITY_99;
        MemberImage::where('member_id', $member_id)->where('type', $type)->delete();

        // ���C���摜�̃`�F�b�N��ۑ����邽�߂̔z��
        $mainImages = $request->input('main_images', []);  // ���ꂪ�`�F�b�N�{�b�N�X����̑I��


        // ���̃f�[�^��ۑ�
        $data = [];
        // for ($i = 0; $i <= FrontConsts::MEMBER_SUB_IMAGE_FILE_CNT; $i++) {
        foreach ($imageOrder as $position => $i) {

            if (!empty($_FILES["file_" . $i]['tmp_name'])) {
                $sub_img = $f->getOriginalFileName($request, "file_" . $i);
                $f->uploade($request, "file_" . $i, FrontConsts::MEMBER_MAIN_IMG_DIR . $member_id . '/');
                $is_main = 0;
                $main_order = null;
            } else if (!empty($request['hidd_file_name' . $i])) {
                $sub_img = $request['hidd_file_name' . $i];
                $is_main = $request['hidd_is_main' . $i];
                $main_order = $request['hidd_main_order' . $i];

            } else {
                continue;
            }

            // ���C���摜�Ƃ��đI�����ꂽ�ꍇ�͂��̏��Ԃ�ۑ�
            //$is_main = in_array($i, $mainImages);
            //$main_order = $is_main ? array_search($i, $mainImages) + 1 : null;

            $data = [
                'type' => $type,
                'status' => \FrontConsts::APPROVAL_OFF,
                'img' => $sub_img,
                'is_main' => $is_main,
                'main_order' => $main_order,
                'status' => '1'
            ];

            MemberImage::updateOrCreate(
                ['member_id' => $member_id, 'type' => $type, 'img' => $sub_img,],
                $data
            );
        }
    }

/*
    public function saveSubImage(Request $request, $member_id)
    {

        $f = new FileUtil();
        $type = \FrontConsts::IDENTITY_99;

        WMemberImage::where('member_id', $member_id)->where('type', $type)->delete();
        MemberImage::where('member_id', $member_id)->where('type', $type)->delete();;
        $request['approval'] = FrontConsts::IDENTITY_99;
        $member = $this->findById($member_id);
        $res = $member->fill($request->except('_token', '_method'))->save();

        $data = [];
        for ($i = 0; $i < FrontConsts::MEMBER_SUB_IMAGE_FILE_CNT; $i++) {
            if (!empty($_FILES["file_" . $i]['tmp_name'])) {
                $sub_img = $f->getOriginalFileName($request, "file_" . $i);
                $f->uploade($request, "file_" . $i, FrontConsts::MEMBER_MAIN_IMG_DIR . $member_id . '/');
            } else if (!empty($request['hidd_file_name' . $i])) {
                $sub_img = $request['hidd_file_name' . $i];
            } else {
                continue;
            }


            $data = ['type' => $type, 'status' => \FrontConsts::APPROVAL_OFF, 'img' => $sub_img];

            WMemberImage::updateOrCreate(
                ['member_id' => $member_id, 'type' => $type, 'img' => $sub_img],
                $data
            );

            MemberImage::updateOrCreate(
                ['member_id' => $member_id, 'type' => $type, 'img' => $sub_img],
                $data
            );
        }
    }
*/

    //
    public function saveAvater(Request $request, $member_id)
    {

        $f = new FileUtil();
        $type = \FrontConsts::IDENTITY_5;

        WMemberImage::where('member_id', $member_id)->where('type', $type)->delete();
        MemberImage::where('member_id', $member_id)->where('type', $type)->delete();

        if (!empty($_FILES["file_1"]['tmp_name'])) {
            $sub_img = $f->getOriginalFileName($request, "file_1");
            $f->uploade($request, "file_1", FrontConsts::MEMBER_MAIN_IMG_DIR . $member_id . '/');
        } else if (!empty($request['hidd_file_name1'])) {
            $sub_img = $request['hidd_file_name1'];
        } else {
            return "";
        }

        $data = ['type' => $type, 'status' => \FrontConsts::APPROVAL_OFF, 'img' => $sub_img];

        WMemberImage::updateOrCreate(
            ['member_id' => $member_id, 'type' => $type, 'img' => $sub_img],
            $data
        );

        MemberImage::updateOrCreate(
            ['member_id' => $member_id, 'type' => $type, 'img' => $sub_img],
            $data
        );
    }

    public function saveMemberByAdmin(Request $request, $member_id)
    {

        if (!empty($request['ng_reason'])) {
            $request['status'] = \FrontConsts::APPROVAL_NG;
        } else {
            $request['status'] = \FrontConsts::APPROVAL_ON;
            $request['ng_reason'] = "";
        }

        $res = $this->syncCastRecord($member_id, $request->all());

        WMember::updateOrCreate(
            ['id' => $member_id],
            $this->findById($member_id)->toArray()
        );
        /*
        if(!empty($request['ng_reason'])) {
            $request['approval'] = \FrontConsts::APPROVAL_NG;
        }else{
            $request['approval'] = \FrontConsts::APPROVAL_OFF2;
        }

        WMember::updateOrCreate(
            ['id' => $member_id],
            $this->findById($member_id)->toArray()
        );
*/
        return $res;
    }


    public function findById($id)
    {
        return $this->findCastRecord($id);
    }


    public function saveMember2(Request $request, $member_id)
    {
        $payload = $request->except('_token', '_method');
        $payload['status'] = FrontConsts::APPROVAL_ON;

        return (bool) $this->syncCastRecord($member_id, $payload);
    }

    public function saveMemberProfileByAdmin(Request $request, $member_id)
    {


        $this->saveMember2($request, $member_id);
        $this->saveMemberIdentity($request, $member_id);
        $this->saveMemberIdentityExp($request, $member_id);
        $this->saveMemberTag($request, $member_id);
        $this->saveAvater($request, $member_id);
        $this->saveSubImage($request, $member_id);
        $this->saveCertificate($request, $member_id);
        BankAccount::updateOrCreate(
            ['member_id' => $member_id],
            $request->all()
        );
    }

    public function findByIdShop($id)
    {
        $records = Cast::where('id', $id)->where('status', \FrontConsts::APPROVAL_ON)->first();
        if (!Cast::where('id', $id)->where('status', \FrontConsts::APPROVAL_ON)->exists()) {
            $records = Cast::where('id', $id)->first();
        }

        return $records ? $this->findCastRecord($records->id) : null;
    }

    public function findByIdShop2($id)
    {
        $records = Cast::where('id', $id)->where('status', \FrontConsts::APPROVAL_ON)->first();
        if (!Cast::where('id', $id)->where('status', \FrontConsts::APPROVAL_ON)->exists()) {
            $records = Cast::where('id', $id)->first();
        }

        return $records ? $this->findCastRecord($records->id) : null;

/*
        $records = Member::select('members.id as member_id', 'tags.id as cast_tag_id', 'tags.content as content')
            ->leftJoin('today_centens_by_members', 'members.id', '=', 'today_centens_by_members.member_id')
            ->leftJoin('member_tags', 'members.id', '=', 'member_tags.member_id')
            ->leftJoin('tags', 'member_tags.tag_id', '=', 'tags.id')
            ->where('members.id', $id)
            ->where('members.approval', \FrontConsts::APPROVAL_ON)
            ->where('members.del_flg', \CommonConsts::DEL_OFF)
            ->get();

            if ($records->isEmpty()) {
                $records = DB::table('w_members')
                ->leftJoin('today_centens_by_members', 'w_members.id', '=', 'today_centens_by_members.member_id')
                ->leftJoin('member_tags', 'w_members.id', '=', 'member_tags.member_id')
                ->leftJoin('tags', 'member_tags.tag_id', '=', 'tags.id')
                ->where('w_members.id', $id)
                //->where('members.approval', \FrontConsts::APPROVAL_ON)
                //->where('members.del_flg', \CommonConsts::DEL_OFF)
                ->get();

                if ($records->isEmpty()) {
                     $records = Member::select('members.id as member_id', 'tags.id as cast_tag_id', 'tags.content as name')
                         ->leftJoin('today_centens_by_members', 'members.id', '=', 'today_centens_by_members.member_id')
                         ->leftJoin('member_tags', 'members.id', '=', 'member_tags.member_id')
                         ->leftJoin('tags', 'member_tags.tag_id', '=', 'tags.id')
                         ->where('members.id', $id)
                         ->get();
                }
            }
*/
        return $records;
    }

    public function getTagContentsByMemberID($id) {

        $records = DB::table('members')
            ->select('tags.content as content')
            ->leftJoin('member_tags', 'members.id', '=', 'member_tags.member_id')
            ->leftJoin('tags', 'member_tags.tag_id', '=', 'tags.id')
            ->where('members.id', $id)
            ->whereIn('type',['casttag','casttag2'])
            ->get();

        return $records;

    }


    public function findMatchingById($member_id)
    {
        //✅ Kết quả trả về:
        //Mỗi shop mà cast đã từng nhắn tin qua messages sẽ chỉ hiển thị 1 lần
        //
        //Nếu shop có apply:
        //
        //Ưu tiên apply active = 1
        //
        //Nếu không có, lấy apply active = 0 mới nhất
        //
        //Nếu shop không có apply, vẫn hiển thị (LEFT JOIN)

        $records = DB::table('messages')
            ->join('shops', 'messages.shop_id', '=', 'shops.id')
            ->where('messages.member_id', $member_id)
            ->leftJoin(DB::raw("
        (
            SELECT *
            FROM applies a
            WHERE a.member_id = {$member_id}
              AND a.id = (
                  SELECT id FROM applies
                  WHERE shop_id = a.shop_id AND member_id = a.member_id
                  ORDER BY active DESC, updated_at DESC
                  LIMIT 1
              )
        ) as applies
    "), 'shops.id', '=', 'applies.shop_id')
            ->leftJoin('deposits', function ($join) {
                $join->on('applies.id', '=', 'deposits.apply_id')
                    ->where('applies.adoption', '=', 1);
            })
            ->select(
                'shops.*',
                'shops.id as shop_id',
                'shops.shop_name',
                'applies.adoption',
                'applies.about_recruit',
                'applies.active',
                'deposits.status as deposits_status'
            )
            ->distinct();


        return $records->paginate(\ShopConsts::PAGENATION_COUNT);
    }


    public function findByIndustry($member_id)
    {

        $records = DB::table('industries')
            ->join('member_industries', 'industries.id', '=', 'member_industries.industry_id')
            ->where('member_industries.member_id', $member_id)
            ->select('industries.name', 'industries.id')
            ->get();
        return $records;
    }

    public function findByIndustryExp($member_id)
    {

        $records = DB::table('industries')
            ->join('member_industry_exps', 'industries.id', '=', 'member_industry_exps.industry_id')
            ->where('member_industry_exps.member_id', $member_id)
            ->select('industries.name')
            ->get();

        return $records;
    }

    public function findCastTagById($member_id, $type = "")
    {

        $records = DB::table('members')
        ->select('members.id as member_id', 'tags.id as cast_tag_id', 'tags.content as name')
        ->join('member_tags', 'members.id', '=', 'member_tags.member_id')
        ->join('tags', 'member_tags.tag_id', '=', 'tags.id')
        ->where('members.id', $member_id);

        if (isset($type) && !empty($type)) {
            $records->where('tags.type', $type);
        }

        return  $records->get();
    }

    public function findFollowMember($shop_id)
    {

        $records = DB::table('members')
            ->join('follows', 'members.id', '=', 'follows.member_id')
            ->join('shops', 'shops.id', '=', 'follows.shop_id')

            ->where('follows.shop_id', $shop_id)
            ->where('shops.id', $shop_id)

            ->select('members.*', 'follows.*', 'members.id as id', 'follows.id as follows_id')
            ->paginate(\ShopConsts::PAGENATION_COUNT);


        return $records;
    }

    public function findMemPhotoById($member_id)
    {

        $records =  DB::table('member_images')->where('member_id', $member_id)->where('type', \FrontConsts::IDENTITY_99)->
                  orderBy('main_order')->select('img')->get();

        return $records;
    }


    public function search(Request $request, $shop_id = "")
    {

        $search = $request->search;


        $query = DB::table('members');

        if ($search) {
            // �S�p�X�y�[�X�𔼊p�ɕϊ�
            $spaceConversion = mb_convert_kana($search, 's');
            // �P��𔼊p�X�y�[�X�ŋ�؂�A�z��ɂ���i��F"AA B" �� ["AA", "B"]�j
            $wordArraySearched = preg_split('/[\s,]+/', $spaceConversion, -1, PREG_SPLIT_NO_EMPTY);
            // �P������[�v�ŉ񂵁A���[�U�[�l�[���ƕ�����v������̂�����΁A$query�Ƃ��ĕێ������
            foreach ($wordArraySearched as $value) {
                $query->where('pref', 'like', '%' . $value . '%')->orWhere('shop_name', 'like', '%' . $value . '%');
            }
        }


        if ($request->id) {
            $query->where('id', $request->id);
        }
        if ($request->nickname) {
            $query->where('nickname', 'like', '%' . $request->nickname . '%');
        }

        if ($request->pref) {
            $query->where('pref', $request->pref);
        }

        if ($request->addr1) {
            $query->where('addr1', $request->addr1);
        }

        if ($request->approval) {
            $query->where('approval', $request->approval);
        }

        if ($request->matching) {
            $query->where('matching', $request->matching);
        }
        if ($request->release) {
            $query->where('release', $request->release);
        }

        if ($request->beroreapproval) {
            $query->where('approval', '<', \ShopConsts::APPROVAL_ON);
        }
        if ($shop_id != "") {
            $query->where('shop_id', $shop_id);
        }

        $records =  $query->get();
        $records = $query->paginate(\ShopConsts::PAGENATION_COUNT);

        return $records;
    }


    public function isApplyShop(Request $request, $member_id, $shop_id)
    {
        $records =  Apply::where('members_id', $member_id)->where('shop_id', $shop_id)->count();

        return $records[0]->coount();
    }

    public function searchMatching(Request $request, $member_id = "")
    {

        $query = DB::table('messages')
            ->join('shops', 'messages.shop_id', '=', 'shops.id')
            ->where('messages.member_id', $member_id)
            ->leftJoin(DB::raw("
        (
            SELECT *
            FROM applies a
            WHERE a.member_id = {$member_id}
              AND a.id = (
                  SELECT id FROM applies
                  WHERE shop_id = a.shop_id AND member_id = a.member_id
                  ORDER BY active DESC, updated_at DESC
                  LIMIT 1
              )
        ) as applies
    "), 'shops.id', '=', 'applies.shop_id')
            ->leftJoin('deposits', function ($join) {
                $join->on('applies.id', '=', 'deposits.apply_id')
                    ->where('applies.adoption', '=', 1);
            })
            ->select(
                'shops.*',
                'shops.id as shop_id',
                'shops.shop_name',
                'applies.adoption',
                'applies.about_recruit',
                'applies.active',
                'deposits.status as deposits_status'
            )
            ->distinct();

        if ($request->filled('shop_id')) {
            $query->where('shops.id', $request->shop_id);
        }
        if ($request->filled('shop_name')) {
            $query->where('shops.shop_name', 'like', '%' . $request->shop_name . '%');
        }
        if ($request->filled('search_area')) {
            $query->where(function ($q) use ($request) {
                $q->where('shops.pref', $request->search_area)
                    ->orWhere('shops.city', $request->search_area);
            });
        }
        if ($request->filled('deposits')) {
            $query->where('deposits.status', $request->deposits);
        }
        if ($request->filled('adoption')) {
            $query->where('applies.adoption', $request->adoption);
        }
        if ($request->filled('recruitment')) {
            $query->where('applies.about_recruit', $request->recruitment);
        }

        $records = $query->paginate(\ShopConsts::PAGENATION_COUNT);

        return $records;
    }


    public function searchReviewMember(Request $request, $shop_id = "")
    {


        $query = DB::table('members')
            ->join('reviews', 'members.id', '=', 'reviews.member_id');

        if (!\StrUtil::is_empty($shop_id)) {
            $query->join('shops', function ($query) {
                $query->on('shops.id', '=', 'reviews.shop_id');
            });
            $query->where('shops.id', $shop_id);
        }

        if ($request->id) {
            $query->where('members.id', $request->id);
        }

        if ($request->nickname) {

            $query->where('members.nickname', 'like', '%' . $request->nickname . '%');
        }

        if ($request->pref) {
            $query->where('members.pref', $request->pref);
        }

        if ($request->recruitment) {
            $query->join('applies', function ($query) {
                $query->on('members.id', '=', 'applies.member_id');
            });
            $query->where('applies.type', $request->recruitment);
        }

        if (!\StrUtil::is_empty($request->deposits)) {
            $query->join('deposits', function ($query) {
                $query->on('members.id', '=', 'deposits.member_id');
            });
            $query->where('deposits.status', $request->deposits);
        }

        if (!\StrUtil::is_empty($request->adoption)) {
            $query->join('adoptions', function ($query) {
                $query->on('members.id', '=', 'adoptions.member_id');
            });
            $query->where('adoptions.result', $request->adoption);
        }


        $records = $query->select('shops.*', 'members.*', 'reviews.*', 'members.id as id', 'reviews.id as review_id');
        $records = $query->paginate(\ShopConsts::PAGENATION_COUNT);


        return $records;
    }

    public function searchFollowMember(Request $request, $shop_id = "")
    {

/*
        $query = DB::table('members')
            ->join('follows', 'members.id', '=', 'follows.member_id');
*/
      $latest_messages = DB::table('follows')
          ->select(DB::raw('MAX(id) as id'))
          ->groupBy('member_id');

      $query = DB::table('follows')
          ->join('shops', 'follows.shop_id', '=', 'shops.id')
          ->joinSub($latest_messages, 'latest_messages', function($join) {
              $join->on('follows.id', '=', 'latest_messages.id');
          })
          ->leftJoin('w_members', 'follows.member_id', '=', 'w_members.id')
          ->leftJoin('members as approved_members', function($join) {
              $join->on('follows.member_id', '=', 'approved_members.id')
                   ->where('approved_members.approval', \FrontConsts::APPROVAL_ON);
          })
          ->leftJoin('members', 'follows.member_id', '=', 'members.id')
          ->where('shops.id', $shop_id);      $latest_messages = DB::table('messages')
          ->select(DB::raw('MAX(id) as id'))
          ->groupBy('member_id');

        if (!\StrUtil::is_empty($shop_id)) {
            $query->where('follows.shop_id', $shop_id);
        }

        if ($request->id) {
          $query->where(function ($query) use ($request) {
              $query->where('approved_members.id', $request->member_id)
                    ->orWhere('w_members.id', $request->member_id)
                    ->orWhere('members.id', $request->member_id);
          });
        }

        if ($request->nickname) {

          $query->where(function ($query) use ($request) {
              $query->where('approved_members.nickname', 'like', '%' . $request->nickname . '%')
                    ->orWhere('w_members.nickname', 'like', '%' . $request->nickname . '%')
                    ->orWhere('members.nickname', 'like', '%' . $request->nickname . '%');
          });
        }

        if ($request->pref) {
          $query->where(function ($query) use ($request) {
              $query->where('approved_members.pref', $request->pref)
                    ->orWhere('w_members.pref', $request->pref)
                    ->orWhere('members.pref', $request->pref);
          });
        }

        if ($request->recruitment) {
            $query->join('applies', function ($query) {
                $query->on('members.id', '=', 'applies.member_id');
            });
            $query->where('applies.type', $request->recruitment);
        }

        if (!\StrUtil::is_empty($request->deposits)) {
            $query->join('deposits', function ($query) {
                $query->on('members.id', '=', 'deposits.member_id');
            });
            $query->where('deposits.status', $request->deposits);
        }

        if (!\StrUtil::is_empty($request->adoption)) {
            $query->join('adoptions', function ($query) {
                $query->on('members.id', '=', 'adoptions.member_id');
            });
            $query->where('adoptions.result', $request->adoption);
        }


          $query->where(function ($query) use ($request) {
              $query->where('approved_members.del_flg', \CommonConsts::DEL_OFF)
                    ->orWhere('w_members.del_flg', \CommonConsts::DEL_OFF)
                    ->orWhere('members.del_flg', \CommonConsts::DEL_OFF);
          });


        //$records = $query->select('members.*', 'follows.*', 'members.id as id', 'follows.id as follows_id');
        //$records = $query->paginate(\ShopConsts::PAGENATION_COUNT);

      $records = $query->select(
          'follows.*',
          DB::raw('COALESCE(approved_members.name, w_members.name, members.name) as name'),
          DB::raw('COALESCE(approved_members.email, w_members.email, members.email) as email'),
          DB::raw('COALESCE(approved_members.nickname, w_members.nickname, members.nickname) as nickname'),
          DB::raw('COALESCE(approved_members.birthday_y, w_members.birthday_y, members.birthday_y) as birthday_y'),
          DB::raw('COALESCE(approved_members.birthday_m, w_members.birthday_m, members.birthday_m) as birthday_m'),
          DB::raw('COALESCE(approved_members.birthday_d, w_members.birthday_d, members.birthday_d) as birthday_d'),
          DB::raw('COALESCE(approved_members.pref, w_members.pref, members.pref) as pref'),
          DB::raw('COALESCE(approved_members.addr1, w_members.addr1, members.addr1) as addr1'),
          'follows.id as follows_id',
          'members.id as members_id'
      )->paginate(ShopConsts::PAGENATION_COUNT);

        return $records;
    }

    public function doFollowByMember($member_id, $enc_shop_id)
    {
        $shoop_id = \StrUtil::dec($enc_shop_id);
        $request['type'] = \CommonConsts::FORROW_BY_SHOP;
        $request = DB::table('follows')->where('member_id', $member_id)->where('shop_id', $shop_id)->get();
        $res = $member->fill($request->except('_token', '_method'))->save();
    }

    public function delete($request, $member_id)
    {
        $member = Cast::find($member_id);
        if (!$member) {
            return;
        }

        $member->deleted_at = now();
        $member->save();
    }

    public function saveApproval($request, $member_id)
    {

        $this->saveMemberByAdmin($request, $member_id);
        /*
        $data = [];

        if(!empty($request['ng_reason'])) {
            $data['approval'] = ShopConsts::APPROVAL_NG;
        }else{
            $data['approval'] = ShopConsts::APPROVAL_ON;
            $data['ng_reason'] = "";
        }

        $member = $this->findById($member_id);
        $line_user_id = $member->line_user_id;
        Member::updateOrCreate(
            ['id' => $member_id,'line_user_id'=>$line_user_id ],
            $data
        );

        WMember::updateOrCreate(
            ['line_user_id'=>$line_user_id ],
            $this->findById($member_id)->toArray()
        );

*/
    }

    public function findParsonalShop($member_id, $lat, $log)
    {

        $query = DB::table('members')->join('member_industries', 'members.id', '=', 'member_industries.member_id')->where('members.id', $member_id)->select("members.*", "members.latitude as latitude", "members.longitude as longitude", "member_industries.industry_id as ind_id");
        $member = $query->get();
        $ind_id_str = "";
        $ind_id_arr = [];

        foreach ($member as $key => $val) {
            if ($val->ind_id) {
                $ind_id_str  .= $val->ind_id . ",";
                $ind_id_arr[] = $val->ind_id;
            }
        }

        $ind_id_str = rtrim($ind_id_str, ",");

        $query = DB::table('shops')->join('shop_profiles', 'shops.id', '=', 'shop_profiles.shop_id')->whereIn('shop_profiles.industry_id', $ind_id_arr);

        if ($lat != "" && !$log != "") {

            $start_latutude = (float)$request->lat;
            $start_longitude = (float)$request->log;
            $earth_r = 6378.137;

            $data = [];
            foreach ($shops as $val) {

                $end_latitude  = (float)$val->latitude;
                $end_longitude = (float)$val->longitude;

                $latitude_margin = deg2rad($end_latitude - $start_latutude);
                $longitide_margin = deg2rad($end_longitude - $start_longitude);
                $south_north = $earth_r * $latitude_margin;
                $west_east = cos(deg2rad($start_latutude)) * $earth_r * $longitide_margin;
                $distance = sqrt(pow($west_east, 2) + pow($south_north, 2));
                $data[] = array("shop_id" => $val->shop_id, "distance" => $distance);
            }
            usort($data, 'compareByAge');

            foreach ($data as $person) {
                echo $person['shop_id'] . ' (' . $person['distance'] . ' years old)' . PHP_EOL;
            }
        } else {


            $shops = $query->orderBy('shops.updated_at')->limit(30)->get();

            return $shops;
        }
    }

    function compareByAge($a, $b)
    {
        return $a['distance'] - $b['distance'];
    }

    public function good2($shop_id, $member_id)
    {
        try {
            Good2::Create(
                ['shop_id' => $shop_id, 'member_id' => $member_id,]
            );
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function good3($shop_id, $member_id)
    {
        try {
        $good2 = Good2::where("shop_id", $shop_id)->where("member_id", $member_id)->first();
        if ($good2) {
            $good2->delete();
        }

        } catch (Exception $e) {
            return false;
        }
        return true;
    }


    public function goodMemberCnt($member_id) {
        return  Good2::where("member_id", $member_id)->count();
    }

    public function likedMemberCnt($member_id) {
        return  Like2::where("member_id", $member_id)->count();
    }

    public function good2ByMyself($member_id)
    {
        try {
            Good2::Create(
                ['member_id' => $member_id,]
            );
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function unGood2ByMyself($member_id)
    {
        try {

            $like = Good2::where("member_id", $member_id)
                ->first();
            if ($like) {
                $like->delete();
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }


    public function like($shop_id, $member_id)
    {
        try {
            Like2::Create(
                ['shop_id' => $shop_id, 'member_id' => $member_id,]
            );
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function unLike($shop_id, $member_id)
    {
        try {

            $like = Like2::where("shop_id", $shop_id)
                ->where("member_id", $member_id)
                ->first();

            if ($like) {
                $like->delete();
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public static function likeShopCnt($member_id)
    {
        $records =  Like2::where('member_id', $member_id)->count();
        return $records;
    }

    public static function goodByMyselfCnt($member_id)
    {
        $records =  Good2::where('member_id', $member_id)->count();
        return $records;
    }


    public function deleteSubImage(Request $request, $member_id, $file_name)
    {

        MemberImage::where("member_id", $member_id)->where("img", $file_name)->delete();
        WMemberImage::where("member_id", $member_id)->where("img", $file_name)->delete();
    }

    public function deleteAvaterImage(Request $request, $member_id, $file_name)
    {

        MemberImage::where('member_id', $member_id)->where('img', $file_name)->delete();
    }

    public function getTodayCentens($member_id)
    {
        return TodayCentensByMember::where("member_id", $member_id)->first();
    }

    public function storeTodayCentens($member_id, $word)
    {

        $res = TodayCentensByMember::updateOrCreate(
            ['member_id' => $member_id],
            ["word" => $word]
        );
    }


    public function deleteMember(Request $request, $member_id){

        $member = Cast::find($member_id);

        if ($member) {
            $member->deleted_at = now();
            $member->save();
        } else {
            return response()->json(['error' => 'Member not found'], 404);
        }


    }


   public function addShopToMember(Request $request)
   {
       $memberId = $request->input('member_id');
       $shopId = $request->input('shop_id');

       // �֌W���쐬�܂��͎擾
       $relationship = ShopMemberRelationship::firstOrCreate([
           'member_id' => $memberId,
           'shop_id' => $shopId,
       ]);

       // �L���X�g���̒ǉ��t���O���X�V
       $relationship->update(['member_added' => true]);

       return response()->json(['message' => 'Shop added by member']);
   }

    private function syncCastRecord(string $memberId, array $payload): ?Cast
    {
        $castPayload = collect($payload)
            ->only([
                'email',
                'password',
                'identity_status',
                'last_login_at',
                'remember_token',
            ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        if (array_key_exists('status', $payload)) {
            $castPayload['status'] = $payload['status'];
        } elseif (array_key_exists('approval', $payload)) {
            $castPayload['status'] = $payload['approval'];
        }

        $cast = Cast::query()->find($memberId);

        if (!$cast) {
            $cast = new Cast();
            $cast->id = $memberId;
        }

        if ($castPayload !== []) {
            $cast->fill($castPayload);
        }

        $cast->save();

        $profilePayload = $this->extractCastProfilePayload($payload);
        if ($profilePayload !== []) {
            DB::table('cast_profiles')->updateOrInsert(
                ['cast_id' => $memberId],
                array_merge(
                    ['created_at' => now()],
                    $profilePayload,
                    ['updated_at' => now()]
                )
            );
        }

        return $this->findCastRecord($memberId);
    }

    private function extractCastProfilePayload(array $payload): array
    {
        $profilePayload = collect([
            'nickname' => $payload['nickname'] ?? null,
            'name' => $payload['name'] ?? null,
            'name_kana' => $payload['name_kana'] ?? ($payload['kana'] ?? null),
            'gender' => $payload['gender'] ?? null,
            'zip' => $payload['zip'] ?? null,
            'pref' => $payload['pref'] ?? null,
            'city' => $payload['city'] ?? null,
            'addr1' => $payload['addr1'] ?? null,
            'addr2' => $payload['addr2'] ?? null,
            'addr3' => $payload['addr3'] ?? null,
            'tel' => $payload['tel'] ?? ($payload['phone'] ?? null),
            'height' => $payload['height'] ?? null,
            'weight' => $payload['weight'] ?? null,
            'bust' => $payload['bust'] ?? ($payload['b'] ?? null),
            'waist' => $payload['waist'] ?? ($payload['w'] ?? null),
            'hip' => $payload['hip'] ?? ($payload['h'] ?? null),
            'profession' => $payload['profession'] ?? null,
            'exp' => $payload['exp'] ?? null,
            'years_exp' => $payload['years_exp'] ?? null,
            'pr' => $payload['pr'] ?? null,
            'charm_point' => $payload['charm_point'] ?? null,
            'memo' => $payload['memo'] ?? null,
            'ng_reason' => $payload['ng_reason'] ?? null,
            'latitude' => $payload['latitude'] ?? null,
            'longitude' => $payload['longitude'] ?? null,
        ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        if (!empty($payload['birthday'])) {
            $profilePayload['birthday'] = $payload['birthday'];
        } elseif (
            !empty($payload['birthday_y'])
            && !empty($payload['birthday_m'])
            && !empty($payload['birthday_d'])
            && checkdate((int) $payload['birthday_m'], (int) $payload['birthday_d'], (int) $payload['birthday_y'])
        ) {
            $profilePayload['birthday'] = sprintf(
                '%04d-%02d-%02d',
                (int) $payload['birthday_y'],
                (int) $payload['birthday_m'],
                (int) $payload['birthday_d']
            );
        }

        return $profilePayload;
    }

    private function findCastRecord(string $memberId): ?Cast
    {
        $cast = Cast::query()->with('profile')->find($memberId);

        if (!$cast) {
            return null;
        }

        $profile = $cast->profile;

        $cast->setAttribute('approval', $cast->status);
        $cast->setAttribute('del_flg', $cast->deleted_at ? CommonConsts::DEL_ON : CommonConsts::DEL_OFF);
        $cast->setAttribute('line_user_id', null);
        $cast->setAttribute('matching', 0);
        $cast->setAttribute('release', 0);

        if ($profile) {
            $cast->setAttribute('nickname', $profile->nickname);
            $cast->setAttribute('name', $profile->name);
            $cast->setAttribute('kana', $profile->name_kana);
            $cast->setAttribute('birthday', $profile->birthday);
            $cast->setAttribute('birthday_y', optional($profile->birthday)?->format('Y'));
            $cast->setAttribute('birthday_m', optional($profile->birthday)?->format('m'));
            $cast->setAttribute('birthday_d', optional($profile->birthday)?->format('d'));
            $cast->setAttribute('gender', $profile->gender);
            $cast->setAttribute('zip', $profile->zip);
            $cast->setAttribute('pref', $profile->pref);
            $cast->setAttribute('city', $profile->city);
            $cast->setAttribute('addr1', $profile->addr1);
            $cast->setAttribute('addr2', $profile->addr2);
            $cast->setAttribute('addr3', $profile->addr3);
            $cast->setAttribute('tel', $profile->tel);
            $cast->setAttribute('height', $profile->height);
            $cast->setAttribute('weight', $profile->weight);
            $cast->setAttribute('b', $profile->bust);
            $cast->setAttribute('w', $profile->waist);
            $cast->setAttribute('h', $profile->hip);
            $cast->setAttribute('profession', $profile->profession);
            $cast->setAttribute('exp', $profile->exp);
            $cast->setAttribute('years_exp', $profile->years_exp);
            $cast->setAttribute('pr', $profile->pr);
            $cast->setAttribute('charm_point', $profile->charm_point);
            $cast->setAttribute('memo', $profile->memo);
            $cast->setAttribute('ng_reason', $profile->ng_reason);
            $cast->setAttribute('latitude', $profile->latitude);
            $cast->setAttribute('longitude', $profile->longitude);
        }

        return $cast;
    }

    public function doBlockByShop($member_id, $shop_id)
    {
        $res = Follow::updateOrCreate(
            ['member_id' => $member_id, 'shop_id' => $shop_id, 'blocked_by' => 'shop'],
            ['blocked_by' => 'shop', 'type' => \CommonConsts::FORROW_BY_SHOP_NG]
        );
    }

    public function doBlockCancelByShop($member_id, $shop_id)
    {
        $res = Follow::updateOrCreate(
            ['member_id' => $member_id, 'shop_id' => $shop_id, 'blocked_by' => 'shop'],
            ['blocked_by' => 'shop', 'type' => \CommonConsts::FORROW_BY_SHOP]
        );
    }


    public function saveReport(Request $request, $shop_id, $member_id)
    {
        Report::Create(
            ['shop_id' => $shop_id, 'member_id' => $member_id, "comment" => $request->comment]
        );
    }

    public function saveMemberImageOrder($request, $member_id)
    {

        // order_data �� JSON ����f�R�[�h
        $orderData = json_decode($request->input('order_data'), true);


        MemberImage::where('member_id', $member_id)
            ->whereIn('type',[\FrontConsts::IDENTITY_5,\FrontConsts::IDENTITY_99])
            ->update(['status'=>'1','is_main'=>'0','type' => \FrontConsts::IDENTITY_99]);

        foreach ($orderData as $data) {

            MemberImage::where('member_id', $member_id)
                ->where('img', $data['sub_img'])
                ->update(['main_order' => $data['display_order'],'is_main'=>'1']);
            if($data['display_order']=='1') {
                MemberImage::where('member_id',$member_id)->where('img',$data['sub_img'])->update(['type' => \FrontConsts::IDENTITY_5]);
            }
        }


    }

    public function checkAvater($request, $member_id)
    {


       $records = MemberImage::where('member_id', $member_id)
           ->whereIn('type', [\FrontConsts::IDENTITY_5])
           ->count();

       if ($records == 0) {
           MemberImage::where('member_id', $member_id)
               ->whereIn('type', [\FrontConsts::IDENTITY_5,\FrontConsts::IDENTITY_99])
               ->orderBy('created_at', 'desc')
               ->limit(1)
               ->update([
                   'type' => \FrontConsts::IDENTITY_5,
                   'is_main' => 1,
               ]);
       }



    }

    public static function getMemberSubImgFilePath3($member_id) {

       $records = MemberImage::where('member_id',$member_id)->whereIn('type',[\FrontConsts::IDENTITY_5,\FrontConsts::IDENTITY_99])
                  ->where('status','1')
                  ->orderByRaw('main_order IS NULL')->orderBy('main_order', 'asc')
                  ->get();
       return $records;
   }

}


