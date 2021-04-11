<?php

namespace App\Insurers\Absolute;


/**
 * Class AbsoluteDict
 * @package App\Insurers\Absolute
 */
class AbsoluteDict
{
    public const PERSON_ROLE_INSURER = '2103';
    public const PERSON_ROLE_OWNER = '2099';
    public const PERSON_ROLE_DRIVER = '2085';

    public const APP_ID = 957591;

    //dictionaries
    public const DICT_MARK_MODEL = 3366;
    public const DICT_DOC_TYPES = 220217;
    public const DICT_USING_GOALS = 2672;
    public const DICT_DOC_TYPES_TO = 222773;
    public const DICT_COLORS = 2028;

    //doc types
//    public const DOC_TYPE_PTS = 220219;
    public const DOC_CAR_TYPES = [
        'pts' => 220219,
        'srts' => 220220,
        'epts' => 225346
    ];

    public const DOC_TO_TYPES = [
        'dcard' => 222777
    ];

    //print doc types
    public const DOC_PICT_TYPE = 'D'; //Вид документа. D – константа. //GETPICTFILES

    //using goals
    public const USE_GOALS = [
        [
            "ISN" => "4978",
            "CODE" => "001",
            "FULLNAME" => "Личная",
            "SHORTNAME" => "ЛИЧНАЯ",
            "N_KIDS" => "0"
        ]
    ];

    //order state
    public const ORDER_PROCESSING = 2516;
    public const ORDER_COMPLETE = 2518;
}