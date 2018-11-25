<?php
namespace CjsMysqli;

function checkMysqli() {

    if (extension_loaded('mysqli')){
        return true;
    }
    return false;
}



function fieldDeal($array) {
    $ret = [
            'fields'=>[],
            'values'=>[],
    ];
    foreach ($array as $key => $val){
        $ret['fields'][] = parseKey($key);
        $ret['values'][] = parseValue($val);
    }
    return $ret;
}


function parseKey($key) {
    $key   =  trim($key);
    if(!preg_match('/[,\'\"\*\(\)`.\s]/',$key)) {
        $key = '`'.$key.'`';
    }
    return $key;
}


function parseValue($value){
    $value = addslashes(stripslashes($value));//重新加斜线，防止从数据库直接读取出错
    return "'".$value."'";
}

function htmlspecialchars($val, $flags = ENT_QUOTES, $encoding = 'utf-8') {
    if(!$val || !is_string($val)) {
        return $val;
    }
    return htmlspecialchars($val, $flags, $encoding);
}


