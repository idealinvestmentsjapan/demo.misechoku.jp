<?php
namespace App\Repositories\Member;

use App\Models\Identity;
use Illuminate\Database\Eloquent\Collection;

interface IdentityRepositoryInterface
{
    public function store(array $data);
    public function findByMemberId($id): Identity;
}


