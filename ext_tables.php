<?php
defined('TYPO3_MODE') || die();

call_user_func(
    function()
    {
        $GLOBALS['TBE_STYLES']['stylesheets']['fal_extra'] = 'EXT:fal_extra/Resources/Public/Css/FalExtra.css';
    }
);