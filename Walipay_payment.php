<?php
// +----------------------------------------------------------------------
// | qianlizeguo
// +----------------------------------------------------------------------
// | 2017-10
// +----------------------------------------------------------------------

$payment_lang = array(
	'name'	=>	'支付宝手机支付(WAP版本)',
	'app_id'	=>	'应用ID,您的APPID',
	'merchant_private_key'	=>	'商户私钥，您的原始格式RSA私钥',
	'alipay_public_key'	=>	'支付宝公钥',

    'signType'	=>	'签名类型',
);
$config = array(
	'app_id'	=>	array(
		'INPUT_TYPE'	=>	'0',
	), //合作者身份ID
    'signType'	=>	array(
        'INPUT_TYPE'	=>	'0'
    ),
	'merchant_private_key'	=>	array(
		'INPUT_TYPE'	=>	'4'
	), //支付宝帐号: 
	//支付宝公钥
	'alipay_public_key'	=>	array(
		'INPUT_TYPE'	=>	'4'
	),
);
/* 模块的基本信息 */
if (isset($read_modules) && $read_modules == true)
{
    $module['class_name']    = 'Walipay';

    /* 名称 */
    $module['name']    = $payment_lang['name'];


    /* 支付方式：1：在线支付；0：线下支付; 2:手机支付 */
    $module['online_pay'] = '2';

    /* 配送 */
    $module['config'] = $config;
    
    $module['lang'] = $payment_lang;
   	$module['reg_url'] = 'http://act.life.alipay.com/systembiz/fangwei/';
    return $module;
}

// 支付宝手机支付模型
require_once(APP_ROOT_PATH.'system/libs/payment.php');
class Walipay_payment implements payment {

	public function get_payment_code($payment_notice_id)
	{
		$payment_notice = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."payment_notice where id = ".$payment_notice_id);
		//$order_sn = $GLOBALS['db']->getOne("select order_sn from ".DB_PREFIX."deal_order where id = ".$payment_notice['order_id']);
		$money = round($payment_notice['money'],2);
		$payment_info = $GLOBALS['db']->getRow("select id,config,logo from ".DB_PREFIX."payment where id=".intval($payment_notice['payment_id']));
		$payment_info['config'] = $config = unserialize($payment_info['config']);


        $subject = $payment_notice['order_sn'];
        $notify_url =  SITE_DOMAIN.APP_ROOT.'/callback/pay/walipay.php?id='.$payment_notice_id;
        $notify_url = str_replace(array("/mapi","/wap"), "", $notify_url);

		//$sql = "select name from ".DB_PREFIX."deal_order_item where order_id =". intval($payment_notice['order_id']);
		//$title_name = $GLOBALS['db']->getOne($sql);


		//$data_return_url = SITE_DOMAIN.APP_ROOT.'/index.php?ctl=payment&act=response&class_name=Walipay';
		$pay = array();
		$pay['subject'] = $subject;
		$pay['body'] = '会员充值';
		$pay['total_fee'] = $money;
		$pay['total_fee_format'] = format_price($money);
		$pay['out_trade_no'] = $payment_notice['notice_sn'];
		$pay['notify_url'] = $notify_url;
		
		$pay['is_wap'] = 1;//
		$pay['pay_code'] = 'walipay';//,支付宝;mtenpay,财付通;mcod,货到付款
				
		return $pay;
	}
	
	public function get_payment(){

		$payment_notice_id = intval($_REQUEST['id']);
		$payment_notice = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."payment_notice where id = ".$payment_notice_id);
		
		$money = round($payment_notice['money'],2);
		$payment_info = $GLOBALS['db']->getRow("select id,config,logo from ".DB_PREFIX."payment where class_name='Walipay'");
		$payment['config'] = $config = unserialize($payment_info['config']);
		
		if(!$payment_info){
			showIpsInfo("不支持的支付方式",wap_url("member","uc_incharge#index"));
			exit();
		}
		
		if (empty($payment_notice)){
			showIpsInfo("支付单号不存在",wap_url("member","uc_incharge#index"));;
			exit();
		}

		/**************************调用授权接口alipay.wap.trade.create.direct获取授权码token**************************/
			
		//服务器异步通知页面路径
		
        $notify_url = SITE_DOMAIN.APP_ROOT.'/callback/pay/walipay_notify.php';
        $notify_url = str_replace(array("/mapi","/wap","/callback/pay/callback/pay/"), array("","","/callback/pay/"), $notify_url);
        
		//需http://格式的完整路径，不允许加?id=123这类自定义参数
		
		//页面跳转同步通知页面路径
		$call_back_url = SITE_DOMAIN.APP_ROOT.'/callback/pay/walipay_response.php';
        $call_back_url = str_replace(array("/mapi","/wap","/callback/pay/callback/pay/"), array("","","/callback/pay/"), $call_back_url);
		
		//需http://格式的完整路径，不允许加?id=123这类自定义参数
		
		//订单名称
		$subject = $payment_notice['notice_sn'];
		
		//必填
		
		//付款金额
		$pay_price = $payment_notice['money'];
		
		//商户订单号
		$out_trade_no = $payment_notice['notice_sn'];
		
		$total_fee = $money;

		//start
        include_once(dirname(__FILE__) . '/alipay/alipay_sdk_sign/AopSdk.php');
        $c = new AopClient;
        $c->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $c->appId = $config['app_id'];
        $c->rsaPrivateKey = $config['merchant_private_key'];
        $c->format = 'json';
        $c->charset= 'UTF-8';
        $c->signType= $config['signType'];
        $c->alipayrsaPublicKey = $config['alipay_public_key'];

        $parameter = array(
            'body' => '会员充值',
            'subject' => $subject,
            'out_trade_no' => $payment_notice['notice_sn'],
            'timeout_express' => '90m',
            'total_amount' => $total_fee,
            'product_code' => 'QUICK_WAP_PAY',
        );

        $request = new AlipayTradeWapPayRequest ();
        $request->setBizContent(json_encode($parameter));
        $request->setNotifyUrl($notify_url);
        $request->setReturnUrl($call_back_url);
        //var_dump($request);die;

        $response= $c->pageExecute($request);
        echo $response;
	}
	
	public function response($request)
	{
	    $request = $_GET;
		$payment_info = $GLOBALS['db']->getRow("select id,config,logo from ".DB_PREFIX."payment where class_name='Walipay'");
		$payment['config'] = $config = unserialize($payment_info['config']);

        include_once(dirname(__FILE__) . '/alipay/alipay_sdk_sign/AopSdk.php');
        $c = new AopClient;
        $c->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $c->appId = $config['app_id'];
        $c->rsaPrivateKey = $config['merchant_private_key'];
        $c->charset= 'UTF-8';
        $c->signType= $config['signType'];
        $c->alipayrsaPublicKey = $config['alipay_public_key'];
        $result = $c->rsaCheckV1($request, $config['alipay_public_key'], $config['sign_type']);

		if($result && $request['app_id'] == $config['app_id']) {//验证成功
			/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
			//请在这里加上商户的业务逻辑程序代码
			
			//——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
		    //获取支付宝的通知返回参数，可参考技术文档中页面跳转同步通知参数列表
		
			//商户订单号
			$out_trade_no = $_GET['out_trade_no'];
		
			//支付宝交易号
			$trade_no = $_GET['trade_no'];
		
			//交易状态
			//$result = $_GET['result'];
		
		
			//判断该笔订单是否在商户网站中已经做过处理
				//如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
				//如果有做过处理，不执行商户的业务程序
			
		
		   $payment_notice = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."payment_notice where notice_sn = '".$out_trade_no."'");
		   //file_put_contents(APP_ROOT_PATH."/alipaylog/payment_notice_sn_3.txt",$payment_notice_sn);
		 
		   require_once APP_ROOT_PATH."system/libs/cart.php";
		   require_once APP_ROOT_PATH."app/Lib/common.php";
		   $rs = payment_paid($payment_notice['id'],$trade_no);					
		   $is_paid = intval($GLOBALS['db']->getOne("select is_paid from ".DB_PREFIX."payment_notice where id = '".intval($payment_notice['id'])."'"));
		   if ($is_paid == 1){	
		   		showIpsInfo("支付成功",wap_url("member","uc_incharge_log#index"));
		   }else{
		   		//file_put_contents(APP_ROOT_PATH."/alipaylog/2.txt","");
				showIpsInfo("支付失败",wap_url("member","uc_incharge#index"));
		   }
		
			
		}
		else {
		    //验证失败
		    //如要调试，请看alipay_notify.php页面的verifyReturn函数
		    showIpsInfo("支付失败",wap_url("member","uc_incharge#index"));
		}
	}
	
	public function notify($request){
		$payment_info = $GLOBALS['db']->getRow("select id,config,logo from ".DB_PREFIX."payment where class_name='Walipay'");
		$payment['config'] = $config = unserialize($payment_info['config']);
		
        include_once(dirname(__FILE__) . '/alipay/alipay_sdk_sign/AopSdk.php');
        $c = new AopClient;
        $c->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $c->appId = $config['app_id'];
        $c->rsaPrivateKey = $config['merchant_private_key'];
        $c->charset= 'UTF-8';
        $c->signType= $config['signType'];
        $c->alipayrsaPublicKey = $config['alipay_public_key'];
        $result = $c->rsaCheckV1($request, $config['alipay_public_key'], $config['sign_type']);

        if($result && $request['app_id'] == $config['app_id']) {//验证成功

			//请在这里加上商户的业务逻辑程序代
			
			
			//——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
			
			//解密（如果是RSA签名需要解密，如果是MD5签名则下面一行清注释掉）
			//$notify_data = decrypt($_POST['notify_data']);
			//$notify_data = $_POST['notify_data'];

            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表

            //解析notify_data
            //注意：该功能PHP5环境及以上支持，需开通curl、SSL等PHP配置环境。建议本地调试时使用PHP开发软件
            //$doc = new DOMDocument();
            //$doc->loadXML($notify_data);

            //商户订单号
            $out_trade_no = $_POST['out_trade_no'];
            //支付宝交易号
            $trade_no = $_POST['trade_no'];

            //交易状态
            $trade_status = $_POST["trade_status"];

            if($trade_status == 'TRADE_FINISHED') {
                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                //如果有做过处理，不执行商户的业务程序

                //注意：
                //该种交易状态只在两种情况下出现
                //1、开通了普通即时到账，买家付款成功后。
                //2、开通了高级即时到账，从该笔交易成功时间算起，过了签约时的可退款时限（如：三个月以内可退款、一年以内可退款等）后。

                //调试用，写文本函数记录程序运行情况是否正常
                //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");

                $payment_notice = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."payment_notice where notice_sn = '".$out_trade_no."'");

                $order_info = $GLOBALS['db']->getRow("select * from ".DB_PREFIX."deal_order where id = ".$payment_notice['order_id']);
                require_once APP_ROOT_PATH."system/libs/cart.php";
                $rs = payment_paid($payment_notice['id'],$trade_no);
                $is_paid = intval($GLOBALS['db']->getOne("select is_paid from ".DB_PREFIX."payment_notice where id = '".intval($payment_notice['id'])."'"));
                if ($is_paid == 1){
                    echo "success";		//请不要修改或删除
                }

            }
            else if ($trade_status == 'TRADE_SUCCESS') {
                echo "success";		//请不要修改或删除
            }


        }
		else {
		    //验证失败
		    echo "fail";
		}
	}
	
	public function get_display_code(){
		return "";
	}
}
?>
