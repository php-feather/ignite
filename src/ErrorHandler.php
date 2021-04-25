<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace Feather\Ignite;

/**
 * Description of ErrorHandler
 *
 * @author fcarbah
 */
interface ErrorHandler
{

    /**
     *
     * @param string|int $errorCode
     * @param string $errorMessage
     * @param string $filename
     * @param int $lineNumber
     * @param array $errorContext
     */
    public function handle($errorCode, $errorMessage, $filename, $lineNumber, $errorContext = array());
}
