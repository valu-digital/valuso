<?php
namespace ValuSo\Exception;

class NotFoundException extends \ValuSo\Exception\ServiceException {
    protected $code = 1003;
}