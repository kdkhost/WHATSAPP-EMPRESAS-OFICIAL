<?php
$ch = curl_init('https://15.235.73.163:2083/execute/CustInfo/displaycontactinfo');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: cpanel balasgal:41ROPEPL3JQB6ZUHJ6PN1JQADLUD5J0Y']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
if (curl_errno($ch)) {
    echo "CURL ERROR: " . curl_error($ch) . "\n";
} else {
    echo "RESPONSE:\n$res\n";
}
