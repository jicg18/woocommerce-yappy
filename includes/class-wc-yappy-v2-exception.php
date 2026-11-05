<?php

defined( 'ABSPATH' ) || exit;

class WC_Yappy_V2_Exception extends Exception {
    protected $yappy_code;

    public function __construct( $message, $yappy_code = '', $code = 0, Throwable $previous = null ) {
        $this->yappy_code = (string) $yappy_code;
        parent::__construct( $message, (int) $code, $previous );
    }

    public function get_yappy_code() {
        return $this->yappy_code;
    }
}
