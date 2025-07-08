<?php

if (!isset($_GET['code'], $_GET['price'], $_GET['from_id'])) die(json_encode(['status' => false, 'msg' => 'Some mandatory parameters have not been sent!', 'code' => 404], 448));

include_once '../config.php';

// بررسی اینکه آیا این کد مربوط به خرید سرویس است یا شارژ کیف پول
$order = $sql->query("SELECT * FROM `orders` WHERE `code` = '{$_GET['code']}' AND `from_id` = '{$_GET['from_id']}' AND `status` = 'pending'");
$factor = $sql->query("SELECT * FROM `factors` WHERE `code` = '{$_GET['code']}'");
$setting = $sql->query("SELECT `zarinpal_token` FROM `payment_setting`")->fetch_assoc();

if ($_GET['Status'] != 'NOK') {
    // اگر سفارش خرید سرویس باشد
    if ($order->num_rows > 0) {
        $order_data = $order->fetch_assoc();
        if (checkZarinpalFactor($setting['zarinpal_token'], $_GET['Authority'], $_GET['price'])) {
            // رفع مشکل اطلاعات ناقص سفارش
            $need_update = false;
            if (empty($order_data['plan']) || empty($order_data['location'])) {
                $service_factor = $sql->query("SELECT * FROM `service_factors` WHERE `code` = '{$_GET['code']}' AND `from_id` = '{$_GET['from_id']}'")->fetch_assoc();
                if ($service_factor) {
                    if (empty($order_data['plan']) && !empty($service_factor['plan'])) {
                        $order_data['plan'] = $service_factor['plan'];
                        $need_update = true;
                    }
                    if (empty($order_data['location']) && !empty($service_factor['location'])) {
                        $order_data['location'] = $service_factor['location'];
                        $need_update = true;
                    }
                    if ($need_update) {
                        $sql->query("UPDATE `orders` SET `plan` = '{$order_data['plan']}', `location` = '{$order_data['location']}' WHERE `code` = '{$_GET['code']}' AND `from_id` = '{$_GET['from_id']}'");
                    }
                }
            }
            
            $user = $sql->query("SELECT * FROM `users` WHERE `from_id` = '{$_GET['from_id']}'")->fetch_assoc();
            $sql->query("UPDATE `orders` SET `status` = 'active' WHERE `code` = '{$_GET['code']}'");
            finalizeOrderAndSendConfig($order_data, $user, $sql, $config);
            $sql->query("DELETE FROM `service_factors` WHERE `from_id` = '{$_GET['from_id']}'");
            
            print '<h2 style="text-align: center; color: black; font-size: 40px; margin-top: 60px;">پرداخت شما با موفقیت انجام شد و سرویس فعال گردید ✅</h2>';
        } else {
            print '<h2 style="text-align: center; color: black; font-size: 40px; margin-top: 60px;">فاکتور پرداخت نشده است ❌</h2>';
        }
    }
    // اگر شارژ کیف پول باشد
    elseif ($factor->num_rows > 0) {
        $factor = $factor->fetch_assoc();
        if ($factor['status'] == 'no') {
            if (checkZarinpalFactor($setting['zarinpal_token'], $_GET['Authority'], $_GET['price'])) {
                $sql->query("UPDATE `factors` SET `status` = 'yes' WHERE `code` = '{$_GET['code']}'");
                $sql->query("UPDATE `users` SET `coin` = coin + {$_GET['price']}, `count_charge` = count_charge + 1 WHERE `from_id` = '{$_GET['from_id']}'");
                sendMessage($_GET['from_id'], "🎯 پرداختی شما با موفقیت انجام شد و حساب شما با موفقیت شارژ شد.\n\n◽مقدار مبلغ : <code>{$_GET['price']}</code>\n◽آیدی عددی : <code>{$_GET['from_id']}</code>");
                sendMessage($config['dev'], "🐝 کاربر جدیدی حساب خود را شارژ کرد!\n\n◽آیدی عددی کاربر : <code>{$_GET['from_id']}</code>\n◽مقدار مبلغ شارژ شده : <code>{$_GET['price']}</code>");
                print '<h2 style="text-align: center; color: black; font-size: 40px; margin-top: 60px;">فاکتور شما با موفقیت تایید شد و حساب شما با موفقیت در ربات شارژ شد ✅</h2>';
            } else {
                print '<h2 style="text-align: center; color: black; font-size: 40px; margin-top: 60px;">فاکتور پرداخت نشده است ❌</h2>';
            }
        } else {
            print '<h2 style="text-align: center; color: black; font-size: 40px; margin-top: 60px;">این فاکتور قبلا در سیستم ثبت شده است ❌</h2>';
        }
    } else {
        print '<h2 style="text-align: center; color: black; font-size: 40px; margin-top: 60px;">فاکتوری با این مشخصات یافت نشد ❌</h2>';
    }
} else {
    print '<h2 style="text-align: center; color: black; font-size: 40px; margin-top: 60px;">فاکتور پرداخت نشده است ❌</h2>';
}

?>