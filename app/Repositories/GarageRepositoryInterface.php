<?php

namespace App\Repositories;

interface GarageRepositoryInterface
{
    public function allSkeleton();

    public function oneSkeleton($id);
}