<?php
// Script start
$rustart = getrusage();
//100 in 140. 50 in 66,71 seconds. 20 urls in 26.5 seconds. 10 urls in 13,16 seconds. 5 urls in 8.0 seconds.  3 in 3.9 seconds.  2 in 4.1 seconds.

include "/home/agnelvishal/Desktop/localhost/news/github/backend/localDbDetails.php";

$time_start = microtime(true);

//proxy details
$ip = '127.0.0.1';
$port = '9051';
$auth = 'password';
$command = 'signal NEWNYM';
$fp = fsockopen($ip,$port,$error_number,$err_string,10);
if(!$fp) { echo "ERROR: $error_number : $err_string"; }
fwrite($fp,"AUTHENTICATE \"".$auth."\"\n");
//  $received = fread($fp,512);
// echo $received;
$v=0;
$curl=array();
$url=array();
$mh = curl_multi_init();
$limit=20;
$item_select = "SELECT `url` FROM `skcript` ORDER BY fbCount,date LIMIT ".$limit;
$result_select = mysqli_query($db, $item_select);

while($row = mysqli_fetch_assoc($result_select))
{
  $url[$v]= $row["url"];
  //echo $url;
  //$url = "http://www.thehindu.com/features/magazine/keeping-the-thriller-alive/article7332623.ece";
  $api = "http://graph.facebook.com/?fields=id,share,og_object%7Blikes.summary(true).limit(0)%7D&id=";

  $request = $api . $url[$v];
  $curl[$v] = curl_init();

  curl_setopt($curl[$v], CURLOPT_URL, $request);
  curl_setopt($curl[$v], CURLOPT_PROXY, "127.0.0.1:9050");
  curl_setopt($curl[$v], CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
  curl_setopt($curl[$v], CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl[$v], CURLOPT_VERBOSE, 0);
  curl_setopt($curl[$v],CURLOPT_COOKIESESSION,1);
  curl_setopt($curl[$v],CURLOPT_FRESH_CONNECT,1);

  curl_multi_add_handle($mh, $curl[$v]);
  $v++;
}
// execute the handles
$running = null;
do {
  curl_multi_exec($mh, $running);
} while($running > 0);


$v=0;
$fblog=-1;
while($v<$limit&&$fblog!=-2)
{
  try
  {
    $response = curl_multi_getcontent($curl[$v]);
    //var_dump($response);
    curl_multi_remove_handle($mh, $curl[$v]);

    // get json as array
    $json = json_decode($response, true);

    $shares = -1;
    $likes = -1;
    $fblog=-1;
    if (!is_null($json))
    {
      if (array_key_exists('share_count',$json['share']))
      {
        $shares = $json['share']['share_count'];
      }


      if (array_key_exists('total_count', $json['og_object']['likes']['summary']))
      {
        $likes = $json['og_object']['likes']['summary']['total_count'];
      }

      if(array_key_exists('error', $json))
      {
        if($json['error']['code']==4||$json['error']['code']==32)
        {
          echo " limit reached ";
          $fblog=-2;
          //proxy. ip is changed here.

          fwrite($fp,$command."\n");
          $received = fread($fp,512);
          echo $received;
          //sleep(3500);
          //	sleep(5);
          continue;
        }
        else
        {
          echo "Some fb api error";
          print_r($json);
          $fblog-3;
        }
      }
    }
    else
    {
      echo "json is null";
      $fblog=-4;
    }

    if($likes>-1||$shares>-1)
    {
      $insertquery="UPDATE skcript SET likes='".$likes."',shares='".$shares."',fbCount=fbCount+1 WHERE url='".$url[$v]."'";
      $insert=mysqli_query($db, $insertquery);
      //echo "UPDATE skcript SET likes='".$likes."',shares='".$shares."',fbCount=fbCount+1 WHERE url='".$url[$v]."'";
      echo "success";
    }
    else
    {
      $insertquery="UPDATE skcript SET fbCount=fbCount+1 WHERE url='".$url[$v]."'";
      $insert=mysqli_query($db, $insertquery);
      echo "failure";
    }


    if (!$insert)
    {
      echo "insert failed for likes and shares - ",mysqli_error($db);
    }


    $v++;

  } catch (Exception $e1)
  {
    echo 'Caught exception: ',  $e1->getMessage(), "\n";
  }
}

curl_multi_close($mh);

// Code ...
echo 'Total execution time in seconds: ' . (microtime(true) - $time_start);
// Script end
function rutime($ru, $rus, $index) {
  return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
  -  ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));
}

$ru = getrusage();
echo "This process used " . rutime($ru, $rustart, "utime") .
" ms for its computations\n";
echo "It spent " . rutime($ru, $rustart, "stime") .
" ms in system calls\n";
mysqli_free_result($result_select);
mysqli_close($db);
?>
