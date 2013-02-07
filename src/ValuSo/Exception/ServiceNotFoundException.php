<?php
namespace ValuSo\Exception;

class ServiceNotFoundException extends NotFoundException {
    protected $code = 1007;
}