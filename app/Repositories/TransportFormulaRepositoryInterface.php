<?php

namespace App\Repositories;

interface TransportFormulaRepositoryInterface
{
    public function readByTransportId($transport_id);

    public function deleteByTransportId($transport_id);

    public function deactivateByTransportId($transport_id);
}