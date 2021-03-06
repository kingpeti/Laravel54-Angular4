<?php

namespace App\Repositories;

interface UserRoleRepositoryInterface
{
    public function allSkeleton();

    public function oneSkeleton($id);

    public function readByUserId($user_id);

    public function deleteByUserId($user_id);

    public function deactivateByUserId($user_id);
}