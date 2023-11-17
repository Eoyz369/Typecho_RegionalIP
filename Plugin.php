<?php

/**
 * Block Mainland China IP + IP Blacklist Access and Redirection
 * @package RegionalIP
 * @author Eoyz369
 * @version 1.0
 * @update: 2023.11.17
 * @link https://github.com/Eoyz369/typecho_RegionalIP
 */
class RegionalIP_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        Typecho_Plugin::factory('index.php')->begin = array('RegionalIP_Plugin', 'RegionalIP');
        Typecho_Plugin::factory('admin/common.php')->begin = array('RegionalIP_Plugin', 'RegionalIP');
        return "启用RegionalIP成功";
    }

    public static function deactivate()
    {
        return "禁用RegionalIP成功";
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        //黑名单列表
        $ips = new Typecho_Widget_Helper_Form_Element_Textarea('ips', null, null, _t('IP黑名单列表'), _t('一行一个，支持规则:<br>以下是例子qwq<br>192.168.1.1<br>210.10.2.1-20<br>222.34.4.*<br>218.192.104.*'));
        $form->addInput($ips);
        //跳转链接
        $location_url = new Typecho_Widget_Helper_Form_Element_Text('location_url', NULL, 'https://www.bing.com/', _t('跳转链接'), '请输入标准的URL地址，IP黑名单的IP访问将会跳转至这个URL');
        $form->addInput($location_url);

    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    public static function RegionalIP()
    {

        if (RegionalIP_Plugin::checkIP()) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $location_url = Typecho_Widget::widget('Widget_Options')->plugin('RegionalIP')->location_url ?: 'https://www.bing.com/';
            Typecho_Cookie::delete('__typecho_uid');
            Typecho_Cookie::delete('__typecho_authCode');
            @session_destroy();
            $user = Typecho_Widget::widget('Widget_User');
            $errorMessage = '抱歉，您的IP<a href="">'. $ip .'</a>'. '段无法访问!';
            echo '<div style="text-align: center; padding: 20px; font-family: Arial, sans-serif; color: #757575;">';
            echo '<h1 style="font-size: 24px; color: #ea4335;">403 Forbidden</h1>';
            echo '<p>' . $errorMessage . '</p>';
            echo '<p>页面将在 <span id="countdown" style="font-weight: bold; color: #4285f4;">3</span> 秒后自动跳转。</p>';
            echo '</div>';
            echo '<script>
                var seconds = 3;
                function countdown() {
                    document.getElementById("countdown").innerText = seconds;
                    if (seconds <= 0) {
                        window.location.href = "' . $location_url . '";
                    } else {
                        seconds--;
                        setTimeout(countdown, 1000);
                    }
                }
                countdown();
            </script>';
            exit;



        }
    }

    private static function checkIP()
    {
        $flag = false;
        $request = new Typecho_Request;
        $ip = trim($request->getIp());
        $iptable = RegionalIP_Plugin::getAllRegionalIP();
        if ($iptable) {
            foreach ($iptable as $value) {
                if (preg_match("{$value}", $ip)) {
                    $flag = true;
                    break;
                }
            }
        }
        //Detect if IP is Mainland China
        $lang = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $login_data = file_get_contents('http://ip-api.com/json/'.$ip);
        $login_country = json_decode($login_data, true);
        $country = $login_country['country'];
        if ($country == 'China') {
            $flag = true;
        }

        return $flag;

    }

    private static function makePregIP($str)
    {
        if (strpos($str, "-") !== false) {
            $aIP = explode(".", $str);
            foreach ($aIP as $key => $value) {
                if (strpos($value, "-") === false) {
                    if ($key == 0) {
                        $preg_limit .= RegionalIP_Plugin::makePregIP($value);
                    } else {
                        $preg_limit .= '.' . RegionalIP_Plugin::makePregIP($value);
                    }

                } else {
                    $aipNum = explode("-", $value);
                    for ($i = $aipNum[0]; $i <= $aipNum[1]; $i++) {
                        $preg .= $preg ? "|" . $i : "[" . $i;
                    }
                    $preg_limit .= strrpos($preg_limit, ".", 1) == (strlen($preg_limit) - 1) ? $preg . "]" : "." . $preg . "]";
                }
            }
        } else {
            $preg_limit .= $str;
        }
        return $preg_limit;
    }

    private static function getAllRegionalIP()
    {
        $config = Typecho_Widget::widget('Widget_Options')->plugin('RegionalIP');
        $ips = $config->ips;
        if ($ips) {
            $ip_array = explode("\n", $ips);
            foreach ($ip_array as $value) {
                $ipaddress = RegionalIP_Plugin::makePregIP($value);
                $ip = str_ireplace(".", "\.", $ipaddress);
                $ip = str_replace("*", "[0-9]{1,3}", $ip);
                $ipaddress = "/" . trim($ip) . "/";
                $ip_list[] = $ipaddress;
            }
        }
        return $ip_list;
    }
}
