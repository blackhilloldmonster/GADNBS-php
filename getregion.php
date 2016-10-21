#!/usr/bin/env php
<?php
//效率不是很高,方法比较粗放,一整套(省-市-县-乡镇)跑完大概需要7272秒
//慎重测试
//最后生成json格式的数据,数据比较大(3.5M)

$start = micro_time_float();
$target = "http://www.stats.gov.cn/tjsj/tjbz/xzqhdm/201608/t20160809_1386477.html";

//获取省市区初始数据
function getFirstData($target)
{
    $target_content = file_get_contents($target);
    $pattern = '/<div class="center_xilan" style="min-height:400px;">(.+?)<div class="center_wenzhang">/is';
    preg_match($pattern, $target_content, $match);
    $target_content = $match[0];
    $target_content = str_replace("<p class=\"MsoNormal\"><span lang","\n<p class=\"MsoNormal\"><span lang",$target_content);
    $target_content = strip_tags($target_content);
    $target_content = str_replace("　"," ",$target_content);
    $target_content = str_replace("&nbsp;&nbsp;&nbsp;&nbsp; ","",$target_content);
    return filterHeader($target_content);
}

function filterHeader($target_content){
    $line_arr = explode("\n",$target_content);
    for($i=0;$i<9;$i++){
        unset($line_arr[$i]);
    }
    unset($line_arr[3523]);
    unset($line_arr[3524]);
    return $line_arr;
}

//拼接省市区数据
function lisToArr($arr)
{
    $province = "";
    $city = "";
    $i = 0;
    $arr_province = [];
    $this_start = micro_time_float();
    foreach($arr as $line){
        $line_arr = explode(" ",$line);
        if(!empty($line_arr[1]) && !isset($line_arr[2]) && !isset($line_arr[3])){
            $i = 0;
            $province = $line_arr[0];
            $arr_province[$province] = [
                'code'=>$province,
                'name'=>str_replace(PHP_EOL, '', $line_arr[1]),
                'parent_code' => '000000',
                'depth'=>1
            ];
            continue;
        }
        if(empty($line_arr[1]) && !empty($line_arr[2]) && !isset($line_arr[3])){
            $i = 0;
            $city = $line_arr[0];
            $arr_province[$province]['detail'][$city] = [
                'code' => $line_arr[0],
                'name' => str_replace(PHP_EOL, '', $line_arr[2]),
                'parent_code' => $province,
                'depth' => 2
            ];
            continue;
        }
        if(empty($line_arr[1]) && empty($line_arr[2] && isset($line_arr[3]))){
            $province_code = substr($line_arr[0], 0, -4);
            $city_code = substr($line_arr[0], 2, -2);
            $target_url = $province_code."/".$city_code."/".$line_arr[0].".html";
            $arr_province[$province]['detail'][$city]['detail'][] = [
                'code' => $line_arr[0],
                'name' => str_replace(PHP_EOL, '', $line_arr[3]),
                'parent_code' => $city,
                'depth' => 3,
                'detail' => handleList($target_url,$line_arr[0])
            ];
            //var_dump($arr_province[$province]['detail'][$city]['detail'][$i]);
            echo "\n".micro_time_float()-$this_start."\n";
            $i++;
            continue;
        }
    }
    return $arr_province;
}

//整理数据去除不必要的键值
function exData($arr)
{
    $newArr = [];
    foreach($arr as $item){
        if(isset($item['detail'])){
            $item['detail_b'] = [];
            foreach($item['detail'] as $item_b){
                $item['detail_b'][] = $item_b;
            }
            $item['detail']=$item['detail_b'];
            unset($item['detail_b']);
        }
        $newArr[] = $item;
    }
    return $newArr;
}

//获取县一下乡镇
function getContent($target_url)
{
    $target_content = file_get_contents($target_url);
    $target_content = mb_convert_encoding($target_content, "UTF-8", "gb2312");
    preg_match('/<tr.*(?=headline)*?<\/tr>/', $target_content, $matches);
    $target_content = str_replace("</a></td><td><a","</a></td> <td><a",$matches[0]);
    $target_content = str_replace("</tr><tr ","</tr>\n<tr ",$target_content);
    $target_content = strip_tags($target_content);
    $target_content = explode("\n", $target_content);
    return $target_content;
}

//拼接乡镇数据
function handleList($target_url ,$qu="000000")
{
    $data = [];
    $url = "http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2015/";
    $target_content = getContent($url.$target_url);
    foreach($target_content as $item){
        list($code, $name) = explode(" ",$item);
        $data[] = [
            'code' => $code,
            'name' => $name,
            'parent_code' => $qu,
            'depth' => 4
        ];

    }
    return $data;
}

//计算时间(微秒)
function micro_time_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
echo "\n 开始处理数据";
$arr = getFirstData($target);
$arr = exData(lisToArr($arr));
echo "\n 数据格式化完成";
$fp=@fopen("./region.json", "a+");
fwrite($fp,json_encode($arr));
echo "\n 数据正在写入文档";
fclose($fp);
echo "\n 处理完成,转换数据并保存为json \n";
echo "\n 耗时:".micro_time_float()-$start."\n";
