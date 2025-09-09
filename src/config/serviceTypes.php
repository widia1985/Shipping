<?php

return [

    'fedex' => [

        // 基础服务
        'FEDEX_GROUND' => ['GROUND SERVICE', 'GROUND DELIVERY'],
        'GROUND_HOME_DELIVERY' => ['HOME DELIVERY', 'RESIDENTIAL DELIVERY'],

        // 國際
        'FEDEX_INTERNATIONAL_PRIORITY' => [
            'INTERNATIONAL PRIORITY',
            'FEDEX INTERNATIONAL PRIORITY'
        ],
        'INTERNATIONAL_PRIORITY_FREIGHT' => [
            'FEDEX INTERNATIONAL PRIORITY FREIGHT',
            'INTERNATIONAL PRIORITY FREIGHT'
        ],
        'INTERNATIONAL_ECONOMY' => [
            'INTERNATIONAL ECONOMY',
            'FEDEX INTERNATIONAL ECONOMY'
        ],
        'INTERNATIONAL_ECONOMY_FREIGHT' => [
            'FEDEX INTERNATIONAL ECONOMY FREIGHT',
            'INTERNATIONAL ECONOMY FREIGHT'
        ],

        // 国内隔夜服务
        'FIRST_OVERNIGHT' => [
            'FEDEX FIRST OVERNIGHT',
            'FIRST OVERNIGHT'
        ],

        'PRIORITY_OVERNIGHT' => [
            'FEDEX PRIORITY OVERNIGHT',
            'PRIORITY OVERNIGHT'
        ],
        'STANDARD_OVERNIGHT' => [
            'FEDEX STANDARD OVERNIGHT',
            'STANDARD OVERNIGHT'
        ],

        // 2天服务
        'FEDEX_2_DAY_AM' => [
            'FEDEX 2DAY A.M.',
            'FEDEX 2DAY AM',
            '2DAY A.M.',
            '2DAY AM',
        ],
        'FEDEX_2_DAY' => [
            'FEDEX 2DAY',
            '2DAY',
        ],

        // 其他快递服务
        'FEDEX_EXPRESS_SAVER' => [
            'FEDEX EXPRESS SAVER',
            'EXPRESS SAVER',
        ],

        // 货运服务
        'FEDEX_FIRST_FREIGHT' => [
            'FEDEX FIRST OVERNIGHT FREIGHT',
            'FIRST OVERNIGHT FREIGHT',
        ],
        'FEDEX_1_DAY_FREIGHT' => [
            'FEDEX 1 DAY FREIGHT',
            'FEDEX ONE DAY FREIGHT',
            'ONE DAY FREIGHT',
        ],
        'FEDEX_2_DAY_FREIGHT' => [
            'FEDEX 2 DAY FREIGHT',
            'FEDEX TWO DAY FREIGHT',
            'TWO DAY FREIGHT',
        ],
        'FEDEX_3_DAY_FREIGHT' => [
            'FEDEX 3 DAY FREIGHT',
            'FEDEX THREE DAY FREIGHT',
            'THREE DAY FREIGHT',
        ],

        // 特殊服务
        'SMART_POST' => ['FEDEX SMART POST', 'SMART POST', 'SMARTPOST'],
    ],
];