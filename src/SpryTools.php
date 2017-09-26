<?php

namespace Spry\SpryProvider;

use Spry\Spry;

class SpryTools {



    public static function get_api_response($request='', $url='')
	{
		if(!empty($request))
		{
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $request);

			$response = curl_exec($ch);
			curl_close($ch);

			return $response;
		}
	}



    /**
	 * Creates a one way Hash value used for Passwords and other authentication.
	 *
 	 * @param string $value
 	 *
 	 * @access 'public'
 	 * @return string
	 */

    public static function hash($value='')
    {
        $salt = '';

		if(isset(Spry::config()->salt))
		{
			$salt = Spry::config()->salt;
		}

		return md5(serialize($value).$salt);
    }



    /**
     * Return a formatted alphnumeric safe version of the string.
     *
     * @param string $string
     *
     * @access 'public'
     * @return string
     */

    public static function sanitize($string)
    {
        return preg_replace("/\W/", '', str_replace([' ', '-'], '_', strtolower(trim($string))));
    }



    /**
	 * Migrates the Database Scheme based on the configuration.
	 *
 	 * @param array $args
 	 *
 	 * @access 'public'
 	 * @return array
	 */

    public static function db_migrate($args=[])
	{
        if(empty(Spry::config()))
        {
            return Spry::response(5001, null);
        }

		if(empty(Spry::config()->db['username']) || empty(Spry::config()->db['database_name']))
        {
            return Spry::response(5032, null);
        }

        if(empty(Spry::config()->db['provider']) || !class_exists(Spry::config()->db['provider']))
        {
            return Spry::response(5033, null);
        }

		$logs = Spry::db()->migrate($args);

		return Spry::response(30, $logs);
	}

    public static function test($test='')
	{
		$response_code = 2050;

        $result = [];

        if(is_string($test))
        {
            if(empty(Spry::config()->tests))
    		{
    			Spry::stop(5052);
    		}

            if(!isset(Spry::config()->tests[$test]))
            {
                return Spry::response(5053, null);
            }

            $test = Spry::config()->tests[$test];
        }

		$result = [
            'status' => 'Passed',
            'params' => $test['params'],
            'expect' => [],
            'result' => [],
        ];

		$response = self::get_api_response(json_encode($test['params']), Spry::config()->endpoint.$test['route']);
		$response = json_decode($response, true);

        $result['full_response'] = $response;

		if(!empty($test['expect']) && is_array($test['expect']))
		{
			$result['result'] = [];

            if(empty($test['expect']))
            {
                $result['status'] = 'Failed';
                $response_code = 5050;
            }
            else
            {
                $result['expect'] = $test['expect'];

				foreach ($test['expect'] as $expect_key => $expect)
				{
					$result['result'][$expect_key] = $response[$expect_key];

					if(empty($response[$expect_key]) || $response[$expect_key] !== $expect)
					{
						$result['status'] = 'Failed';
						$response_code = 5050;
					}
				}
            }
		}

		return Spry::response($response_code, $result);
	}

    public static function authenticateWebTools()
	{
		$enabled = !empty(Spry::config()->webtools_enabled);

		if(!$enabled)
		{
			return false;
		}

		$endpoint = !empty(Spry::config()->webtools_endpoint) ? trim(Spry::config()->webtools_endpoint) : '';
		$username = !empty(Spry::config()->webtools_username) ? Spry::config()->webtools_username : '';
		$password = !empty(Spry::config()->webtools_password) ? Spry::config()->webtools_password : '';
		$ips = !empty(Spry::config()->webtools_allowed_ips) ? Spry::config()->webtools_allowed_ips : [];

		// Check for Values
		if('/'.trim($endpoint, '/').'/' !== Spry::get_path() ||
		   !$username ||
		   !$password ||
		   !$endpoint ||
		   empty($ips) ||
		   !in_array($_SERVER['REMOTE_ADDR'], $ips))
		{
			return false;
		}

		if(empty($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== $username || empty($_SERVER['PHP_AUTH_PW']) || $_SERVER['PHP_AUTH_PW'] !== $password)
		{
			sleep(1);
	        header('WWW-Authenticate: Basic realm="Authenticate"');
	        header('HTTP/1.0 401 Unauthorized');
	        return false;
		}

		return true;
	}



    public static function webTools()
    {
        if(!self::authenticateWebTools())
		{
			return;
		}

		$controller = Spry::get_controller('Spry\\SpryProvider\\SpryTools::displayWebTools');
		Spry::get_response($controller);

    }



	public static function displayWebTools()
	{
		if(!empty($_POST['ajax']))
		{
			$ajax = $_POST['ajax'];

			if($ajax === 'hash')
			{
				if(!empty($_POST['hash']))
				{
					die(self::get_hash($_POST['hash']));
				}
			}

            if($ajax === 'build_tests_response')
			{
				if(!empty($_POST['response_code']) && !empty($_POST['results']))
				{
					Spry::send_response(Spry::response($_POST['response_code'], $_POST['results']));
					exit;
				}
            }

            if($ajax === 'get_tests')
			{
                Spry::send_response(Spry::response(2000, Spry::config()->tests));
                exit;
			}

			if($ajax === 'db_migrate')
			{
				$destructive = !empty($_POST['destructive']) ? true : false;
				$dryrun = !empty($_POST['dryrun']) ? true : false;
				$results = self::db_migrate(['destructive' => $destructive, 'dryrun' => $dryrun]);
				Spry::send_response($results);
				exit;
			}

			if($ajax === 'php_logs')
			{
				die((file_exists(Spry::config()->log_php_file) ? file_get_contents(Spry::config()->log_php_file) : ''));
			}

			if($ajax === 'api_logs')
			{
				die((file_exists(Spry::config()->log_api_file) ? file_get_contents(Spry::config()->log_api_file) : ''));
			}

			if($ajax === 'clear_php_logs')
			{
				if(file_exists(Spry::config()->log_php_file))
				{
					file_put_contents(Spry::config()->log_php_file, '');
				}
			}

			if($ajax === 'clear_api_logs')
			{
				if(file_exists(Spry::config()->log_api_file))
				{
					file_put_contents(Spry::config()->log_api_file, '');
				}
			}

			exit;
		}

		if(!empty($_GET['clear_log']) && !empty(Spry::config()->log_php_file))
		{
			file_put_contents(Spry::config()->log_php_file, '');
			header("Location: ".$_SERVER['PHP_SELF']);
		}

		?>

		<style>

		/* Reset */
		a,abbr,acronym,address,applet,article,aside,audio,b,big,blockquote,body,canvas,caption,center,cite,code,dd,del,details,dfn,div,dl,dt,em,embed,fieldset,figcaption,figure,footer,form,h1,h2,h3,h4,h5,h6,header,hgroup,html,i,iframe,img,ins,kbd,label,legend,li,mark,menu,nav,object,ol,output,p,pre,q,ruby,s,samp,section,small,span,strike,strong,sub,summary,sup,table,tbody,td,tfoot,th,thead,time,tr,tt,u,ul,var,video{margin:0;padding:0;border:0;font:inherit;vertical-align:baseline}article,aside,details,figcaption,figure,footer,header,hgroup,menu,nav,section{display:block}body{line-height:1}ol,ul{list-style:none}blockquote,q{quotes:none}blockquote:after,blockquote:before,q:after,q:before{content:'';content:none}table{border-collapse:collapse;border-spacing:0}

		body,
		html {
			height: 100%;
			background-color: #303030  !important;
		}

		* {
			background-color: transparent;
			color: #eee;
			margin: 0;
			box-sizing: border-box;
		}
		*:focus,
		*:active {
		    outline: none;
		    outline-style: none;
		}
		body {
			padding: 1%;
			font: normal normal normal 16px / 20px "arail", sans-serif;
		}
		textarea,
		select,
		input[type="text"],
		input[type="password"],
		button,
		.tabs li,
		.container,
		.content {
			border: 1px solid #777;
			-webkit-appearance: none;
			-moz-appearance:    none;
			appearance:         none;
			padding: 4px 10px;
			font-size: .7rem;
			background: transparent;
			height: 28px;
			line-height: 15px;
		}
		span.select select {
			padding-right: 20px;
		}
		span.select {
			position: relative;
		}
		span.select::before {
			content: '';
			display: inline-block;
			position: absolute;
			top: 10px;
			right: 100px;
			color: #777;
			width: 0.4em;
		    height: 0.4em;
		    border-right: 1px solid #777;
		    border-top: 1px solid #777;
		    transform: rotate(135deg);
		    margin-right: 0.5em;
		}
		span.submit button {
			padding-right: 20px;
			cursor: pointer;
		}
		span.submit {
			position: relative;
		}
		span.submit::before {
			content: '';
			display: inline-block;
			position: absolute;
			top: 12px;
			right: 8px;
			color: #777;
			width: 0.4em;
		    height: 0.4em;
		    border-right: 1px solid #777;
		    border-top: 1px solid #777;
		    transform: rotate(45deg);
		    margin-right: 0.5em;
		}
		textarea {
			background: #2a2a2a;
			border-color: #555;
			width: 100%;
			height: 100%;
			max-width: 100%;
			max-height: 100%;
			border-radius: 3px;
		}
		.container {
			overflow: auto;
			height: 100%;
		}

		.tabs {
			list-style: none;
			text-align: left;
			padding: 0;
			height:
		}
		.tabs li {
			display: inline-block;
			cursor: pointer;
			color: #aaa;
			line-height: 16px;
		}
		.tabs li:hover,
		.tabs li.active,
		select:hover,
		button:hover {
			color: #eee;
			border-color: #aaa;
			cursor: pointer;
		}
		.tab-content {
			display: none;
			margin-top: -20px;
			padding-top: 30px;
			background: transparent;
			height: 99%;
			overflow: hidden;
		}
		.tab-content[data-tab="tester"] {
			display: block;
		}
		.top-section {
			height: 70%;
			clear: bloth;
		}
		.bottom-section {
			height: 30%;
			clear: bloth;
			padding-top: 8px;
		}
		.api-form span.submit button,
		.api-form span.select select {
			width: 100%;
		}
		.api-form span.submit,
		.api-form span.select {
			display: block;
			float: left;
		}
		.api-form span.submit {
			width: 90px;
		}
		.api-form span.select {
			width: 100%;
			margin-right: -90px;
		}
		.left-section fieldset  {
			border-right: 0;
		}
		.api-request-text-container {
			height: 100%;
			padding-top: 28px;
		}
		.left-section {
			float:left; width: 40%;
		}
		.right-section {
			float:left; width: 60%;
		}
		.bottom-section .left-section {
			float:left; width: 50%;
			padding-right: 12px;
		}
		.bottom-section .left-section fieldset {
			border-right: 1px solid #222;
		}
		.bottom-section .right-section {
			float:left; width: 50%;
		}
		.clear-php-logs,
		.clear-api-logs {
			padding: 0 0px;
			line-height: 1;
			height: 15px;
			border: 0;
			color: #777;
		}
		fieldset {
			border: 1px solid #222;
			border-radius: 3px;
			padding: 10px 5px 10px 5px;
			height: 100%;
			min-width: 0;
    		width: 100%;
		}
		.success {
			color: #0f5;
		}
		.error {
			color: #f11;
		}
		.unknown {
			color: #880;
		}
		legend {
			padding: 0 10px;
			font-size: .8rem;
			color: #79a;
			line-height: 1;
		}
		legend .status {
			padding: 0 6px 0 12px;
			font-weight: bold;
		}
		.content {
			overflow: hidden;
			height: 100%;
			border: none;
		}

		.loader {
			border: 1px solid #555;
			border-radius: 50%;
			border-top: 1px solid #eee;
			width: 10px;
			height: 10px;
			-webkit-animation: spin 2s linear infinite;
			animation: spin 0.75s linear infinite;
			display: inline-block;
			margin-left: 10px;
		}

		@-webkit-keyframes spin {
			0% { -webkit-transform: rotate(0deg); }
			100% { -webkit-transform: rotate(360deg); }
		}

		@keyframes spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}

		textarea,
		pre {
		    -moz-tab-size : 4;
		      -o-tab-size : 4;
		         tab-size : 4;
		}
		select:-moz-focusring {
		    color: transparent;
		    text-shadow: 0 0 0 #eee;
		}
		*,
		*:active {
			outline: none !important;
		}
		button::-moz-focus-inner {
		  border: 0;
		}
		option {
			color: black;
			background: #aaa;
		}
		.migrate-controls {
			padding-bottom: 10px;
		}
		.migrate-controls::after{
			content: '';
			display: block;
			float: none;
			clear: both;
		}

		.migrate-controls .submit {
			float: right;
		}

		.migrate-controls .submit button {
			padding-right: 30px
		}

		label {
			display: inline-block;
			padding-left: 15px;
			text-indent: -15px;
			margin: 6px 30px 0 0 ;
		}
		label input {
			width: 13px;
			height: 13px;
			padding: 0;
			margin: 0 10px 0 0;
			vertical-align: bottom;
			position: relative;
			top: -1px;
			*overflow: hidden;
		}


		</style>

		<script>

		/* Zepto v1.2.0 - zepto event ajax form ie - zeptojs.com/license */
		!function(t,e){"function"==typeof define&&define.amd?define(function(){return e(t)}):e(t)}(this,function(t){var e=function(){function $(t){return null==t?String(t):S[C.call(t)]||"object"}function F(t){return"function"==$(t)}function k(t){return null!=t&&t==t.window}function M(t){return null!=t&&t.nodeType==t.DOCUMENT_NODE}function R(t){return"object"==$(t)}function Z(t){return R(t)&&!k(t)&&Object.getPrototypeOf(t)==Object.prototype}function z(t){var e=!!t&&"length"in t&&t.length,n=r.type(t);return"function"!=n&&!k(t)&&("array"==n||0===e||"number"==typeof e&&e>0&&e-1 in t)}function q(t){return a.call(t,function(t){return null!=t})}function H(t){return t.length>0?r.fn.concat.apply([],t):t}function I(t){return t.replace(/::/g,"/").replace(/([A-Z]+)([A-Z][a-z])/g,"$1_$2").replace(/([a-z\d])([A-Z])/g,"$1_$2").replace(/_/g,"-").toLowerCase()}function V(t){return t in l?l[t]:l[t]=new RegExp("(^|\\s)"+t+"(\\s|$)")}function _(t,e){return"number"!=typeof e||h[I(t)]?e:e+"px"}function B(t){var e,n;return c[t]||(e=f.createElement(t),f.body.appendChild(e),n=getComputedStyle(e,"").getPropertyValue("display"),e.parentNode.removeChild(e),"none"==n&&(n="block"),c[t]=n),c[t]}function U(t){return"children"in t?u.call(t.children):r.map(t.childNodes,function(t){return 1==t.nodeType?t:void 0})}function X(t,e){var n,r=t?t.length:0;for(n=0;r>n;n++)this[n]=t[n];this.length=r,this.selector=e||""}function J(t,r,i){for(n in r)i&&(Z(r[n])||L(r[n]))?(Z(r[n])&&!Z(t[n])&&(t[n]={}),L(r[n])&&!L(t[n])&&(t[n]=[]),J(t[n],r[n],i)):r[n]!==e&&(t[n]=r[n])}function W(t,e){return null==e?r(t):r(t).filter(e)}function Y(t,e,n,r){return F(e)?e.call(t,n,r):e}function G(t,e,n){null==n?t.removeAttribute(e):t.setAttribute(e,n)}function K(t,n){var r=t.className||"",i=r&&r.baseVal!==e;return n===e?i?r.baseVal:r:void(i?r.baseVal=n:t.className=n)}function Q(t){try{return t?"true"==t||("false"==t?!1:"null"==t?null:+t+""==t?+t:/^[\[\{]/.test(t)?r.parseJSON(t):t):t}catch(e){return t}}function tt(t,e){e(t);for(var n=0,r=t.childNodes.length;r>n;n++)tt(t.childNodes[n],e)}var e,n,r,i,O,P,o=[],s=o.concat,a=o.filter,u=o.slice,f=t.document,c={},l={},h={"column-count":1,columns:1,"font-weight":1,"line-height":1,opacity:1,"z-index":1,zoom:1},p=/^\s*<(\w+|!)[^>]*>/,d=/^<(\w+)\s*\/?>(?:<\/\1>|)$/,m=/<(?!area|br|col|embed|hr|img|input|link|meta|param)(([\w:]+)[^>]*)\/>/gi,g=/^(?:body|html)$/i,v=/([A-Z])/g,y=["val","css","html","text","data","width","height","offset"],x=["after","prepend","before","append"],b=f.createElement("table"),E=f.createElement("tr"),j={tr:f.createElement("tbody"),tbody:b,thead:b,tfoot:b,td:E,th:E,"*":f.createElement("div")},w=/complete|loaded|interactive/,T=/^[\w-]*$/,S={},C=S.toString,N={},A=f.createElement("div"),D={tabindex:"tabIndex",readonly:"readOnly","for":"htmlFor","class":"className",maxlength:"maxLength",cellspacing:"cellSpacing",cellpadding:"cellPadding",rowspan:"rowSpan",colspan:"colSpan",usemap:"useMap",frameborder:"frameBorder",contenteditable:"contentEditable"},L=Array.isArray||function(t){return t instanceof Array};return N.matches=function(t,e){if(!e||!t||1!==t.nodeType)return!1;var n=t.matches||t.webkitMatchesSelector||t.mozMatchesSelector||t.oMatchesSelector||t.matchesSelector;if(n)return n.call(t,e);var r,i=t.parentNode,o=!i;return o&&(i=A).appendChild(t),r=~N.qsa(i,e).indexOf(t),o&&A.removeChild(t),r},O=function(t){return t.replace(/-+(.)?/g,function(t,e){return e?e.toUpperCase():""})},P=function(t){return a.call(t,function(e,n){return t.indexOf(e)==n})},N.fragment=function(t,n,i){var o,s,a;return d.test(t)&&(o=r(f.createElement(RegExp.$1))),o||(t.replace&&(t=t.replace(m,"<$1></$2>")),n===e&&(n=p.test(t)&&RegExp.$1),n in j||(n="*"),a=j[n],a.innerHTML=""+t,o=r.each(u.call(a.childNodes),function(){a.removeChild(this)})),Z(i)&&(s=r(o),r.each(i,function(t,e){y.indexOf(t)>-1?s[t](e):s.attr(t,e)})),o},N.Z=function(t,e){return new X(t,e)},N.isZ=function(t){return t instanceof N.Z},N.init=function(t,n){var i;if(!t)return N.Z();if("string"==typeof t)if(t=t.trim(),"<"==t[0]&&p.test(t))i=N.fragment(t,RegExp.$1,n),t=null;else{if(n!==e)return r(n).find(t);i=N.qsa(f,t)}else{if(F(t))return r(f).ready(t);if(N.isZ(t))return t;if(L(t))i=q(t);else if(R(t))i=[t],t=null;else if(p.test(t))i=N.fragment(t.trim(),RegExp.$1,n),t=null;else{if(n!==e)return r(n).find(t);i=N.qsa(f,t)}}return N.Z(i,t)},r=function(t,e){return N.init(t,e)},r.extend=function(t){var e,n=u.call(arguments,1);return"boolean"==typeof t&&(e=t,t=n.shift()),n.forEach(function(n){J(t,n,e)}),t},N.qsa=function(t,e){var n,r="#"==e[0],i=!r&&"."==e[0],o=r||i?e.slice(1):e,s=T.test(o);return t.getElementById&&s&&r?(n=t.getElementById(o))?[n]:[]:1!==t.nodeType&&9!==t.nodeType&&11!==t.nodeType?[]:u.call(s&&!r&&t.getElementsByClassName?i?t.getElementsByClassName(o):t.getElementsByTagName(e):t.querySelectorAll(e))},r.contains=f.documentElement.contains?function(t,e){return t!==e&&t.contains(e)}:function(t,e){for(;e&&(e=e.parentNode);)if(e===t)return!0;return!1},r.type=$,r.isFunction=F,r.isWindow=k,r.isArray=L,r.isPlainObject=Z,r.isEmptyObject=function(t){var e;for(e in t)return!1;return!0},r.isNumeric=function(t){var e=Number(t),n=typeof t;return null!=t&&"boolean"!=n&&("string"!=n||t.length)&&!isNaN(e)&&isFinite(e)||!1},r.inArray=function(t,e,n){return o.indexOf.call(e,t,n)},r.camelCase=O,r.trim=function(t){return null==t?"":String.prototype.trim.call(t)},r.uuid=0,r.support={},r.expr={},r.noop=function(){},r.map=function(t,e){var n,i,o,r=[];if(z(t))for(i=0;i<t.length;i++)n=e(t[i],i),null!=n&&r.push(n);else for(o in t)n=e(t[o],o),null!=n&&r.push(n);return H(r)},r.each=function(t,e){var n,r;if(z(t)){for(n=0;n<t.length;n++)if(e.call(t[n],n,t[n])===!1)return t}else for(r in t)if(e.call(t[r],r,t[r])===!1)return t;return t},r.grep=function(t,e){return a.call(t,e)},t.JSON&&(r.parseJSON=JSON.parse),r.each("Boolean Number String Function Array Date RegExp Object Error".split(" "),function(t,e){S["[object "+e+"]"]=e.toLowerCase()}),r.fn={constructor:N.Z,length:0,forEach:o.forEach,reduce:o.reduce,push:o.push,sort:o.sort,splice:o.splice,indexOf:o.indexOf,concat:function(){var t,e,n=[];for(t=0;t<arguments.length;t++)e=arguments[t],n[t]=N.isZ(e)?e.toArray():e;return s.apply(N.isZ(this)?this.toArray():this,n)},map:function(t){return r(r.map(this,function(e,n){return t.call(e,n,e)}))},slice:function(){return r(u.apply(this,arguments))},ready:function(t){return w.test(f.readyState)&&f.body?t(r):f.addEventListener("DOMContentLoaded",function(){t(r)},!1),this},get:function(t){return t===e?u.call(this):this[t>=0?t:t+this.length]},toArray:function(){return this.get()},size:function(){return this.length},remove:function(){return this.each(function(){null!=this.parentNode&&this.parentNode.removeChild(this)})},each:function(t){return o.every.call(this,function(e,n){return t.call(e,n,e)!==!1}),this},filter:function(t){return F(t)?this.not(this.not(t)):r(a.call(this,function(e){return N.matches(e,t)}))},add:function(t,e){return r(P(this.concat(r(t,e))))},is:function(t){return this.length>0&&N.matches(this[0],t)},not:function(t){var n=[];if(F(t)&&t.call!==e)this.each(function(e){t.call(this,e)||n.push(this)});else{var i="string"==typeof t?this.filter(t):z(t)&&F(t.item)?u.call(t):r(t);this.forEach(function(t){i.indexOf(t)<0&&n.push(t)})}return r(n)},has:function(t){return this.filter(function(){return R(t)?r.contains(this,t):r(this).find(t).size()})},eq:function(t){return-1===t?this.slice(t):this.slice(t,+t+1)},first:function(){var t=this[0];return t&&!R(t)?t:r(t)},last:function(){var t=this[this.length-1];return t&&!R(t)?t:r(t)},find:function(t){var e,n=this;return e=t?"object"==typeof t?r(t).filter(function(){var t=this;return o.some.call(n,function(e){return r.contains(e,t)})}):1==this.length?r(N.qsa(this[0],t)):this.map(function(){return N.qsa(this,t)}):r()},closest:function(t,e){var n=[],i="object"==typeof t&&r(t);return this.each(function(r,o){for(;o&&!(i?i.indexOf(o)>=0:N.matches(o,t));)o=o!==e&&!M(o)&&o.parentNode;o&&n.indexOf(o)<0&&n.push(o)}),r(n)},parents:function(t){for(var e=[],n=this;n.length>0;)n=r.map(n,function(t){return(t=t.parentNode)&&!M(t)&&e.indexOf(t)<0?(e.push(t),t):void 0});return W(e,t)},parent:function(t){return W(P(this.pluck("parentNode")),t)},children:function(t){return W(this.map(function(){return U(this)}),t)},contents:function(){return this.map(function(){return this.contentDocument||u.call(this.childNodes)})},siblings:function(t){return W(this.map(function(t,e){return a.call(U(e.parentNode),function(t){return t!==e})}),t)},empty:function(){return this.each(function(){this.innerHTML=""})},pluck:function(t){return r.map(this,function(e){return e[t]})},show:function(){return this.each(function(){"none"==this.style.display&&(this.style.display=""),"none"==getComputedStyle(this,"").getPropertyValue("display")&&(this.style.display=B(this.nodeName))})},replaceWith:function(t){return this.before(t).remove()},wrap:function(t){var e=F(t);if(this[0]&&!e)var n=r(t).get(0),i=n.parentNode||this.length>1;return this.each(function(o){r(this).wrapAll(e?t.call(this,o):i?n.cloneNode(!0):n)})},wrapAll:function(t){if(this[0]){r(this[0]).before(t=r(t));for(var e;(e=t.children()).length;)t=e.first();r(t).append(this)}return this},wrapInner:function(t){var e=F(t);return this.each(function(n){var i=r(this),o=i.contents(),s=e?t.call(this,n):t;o.length?o.wrapAll(s):i.append(s)})},unwrap:function(){return this.parent().each(function(){r(this).replaceWith(r(this).children())}),this},clone:function(){return this.map(function(){return this.cloneNode(!0)})},hide:function(){return this.css("display","none")},toggle:function(t){return this.each(function(){var n=r(this);(t===e?"none"==n.css("display"):t)?n.show():n.hide()})},prev:function(t){return r(this.pluck("previousElementSibling")).filter(t||"*")},next:function(t){return r(this.pluck("nextElementSibling")).filter(t||"*")},html:function(t){return 0 in arguments?this.each(function(e){var n=this.innerHTML;r(this).empty().append(Y(this,t,e,n))}):0 in this?this[0].innerHTML:null},text:function(t){return 0 in arguments?this.each(function(e){var n=Y(this,t,e,this.textContent);this.textContent=null==n?"":""+n}):0 in this?this.pluck("textContent").join(""):null},attr:function(t,r){var i;return"string"!=typeof t||1 in arguments?this.each(function(e){if(1===this.nodeType)if(R(t))for(n in t)G(this,n,t[n]);else G(this,t,Y(this,r,e,this.getAttribute(t)))}):0 in this&&1==this[0].nodeType&&null!=(i=this[0].getAttribute(t))?i:e},removeAttr:function(t){return this.each(function(){1===this.nodeType&&t.split(" ").forEach(function(t){G(this,t)},this)})},prop:function(t,e){return t=D[t]||t,1 in arguments?this.each(function(n){this[t]=Y(this,e,n,this[t])}):this[0]&&this[0][t]},removeProp:function(t){return t=D[t]||t,this.each(function(){delete this[t]})},data:function(t,n){var r="data-"+t.replace(v,"-$1").toLowerCase(),i=1 in arguments?this.attr(r,n):this.attr(r);return null!==i?Q(i):e},val:function(t){return 0 in arguments?(null==t&&(t=""),this.each(function(e){this.value=Y(this,t,e,this.value)})):this[0]&&(this[0].multiple?r(this[0]).find("option").filter(function(){return this.selected}).pluck("value"):this[0].value)},offset:function(e){if(e)return this.each(function(t){var n=r(this),i=Y(this,e,t,n.offset()),o=n.offsetParent().offset(),s={top:i.top-o.top,left:i.left-o.left};"static"==n.css("position")&&(s.position="relative"),n.css(s)});if(!this.length)return null;if(f.documentElement!==this[0]&&!r.contains(f.documentElement,this[0]))return{top:0,left:0};var n=this[0].getBoundingClientRect();return{left:n.left+t.pageXOffset,top:n.top+t.pageYOffset,width:Math.round(n.width),height:Math.round(n.height)}},css:function(t,e){if(arguments.length<2){var i=this[0];if("string"==typeof t){if(!i)return;return i.style[O(t)]||getComputedStyle(i,"").getPropertyValue(t)}if(L(t)){if(!i)return;var o={},s=getComputedStyle(i,"");return r.each(t,function(t,e){o[e]=i.style[O(e)]||s.getPropertyValue(e)}),o}}var a="";if("string"==$(t))e||0===e?a=I(t)+":"+_(t,e):this.each(function(){this.style.removeProperty(I(t))});else for(n in t)t[n]||0===t[n]?a+=I(n)+":"+_(n,t[n])+";":this.each(function(){this.style.removeProperty(I(n))});return this.each(function(){this.style.cssText+=";"+a})},index:function(t){return t?this.indexOf(r(t)[0]):this.parent().children().indexOf(this[0])},hasClass:function(t){return t?o.some.call(this,function(t){return this.test(K(t))},V(t)):!1},addClass:function(t){return t?this.each(function(e){if("className"in this){i=[];var n=K(this),o=Y(this,t,e,n);o.split(/\s+/g).forEach(function(t){r(this).hasClass(t)||i.push(t)},this),i.length&&K(this,n+(n?" ":"")+i.join(" "))}}):this},removeClass:function(t){return this.each(function(n){if("className"in this){if(t===e)return K(this,"");i=K(this),Y(this,t,n,i).split(/\s+/g).forEach(function(t){i=i.replace(V(t)," ")}),K(this,i.trim())}})},toggleClass:function(t,n){return t?this.each(function(i){var o=r(this),s=Y(this,t,i,K(this));s.split(/\s+/g).forEach(function(t){(n===e?!o.hasClass(t):n)?o.addClass(t):o.removeClass(t)})}):this},scrollTop:function(t){if(this.length){var n="scrollTop"in this[0];return t===e?n?this[0].scrollTop:this[0].pageYOffset:this.each(n?function(){this.scrollTop=t}:function(){this.scrollTo(this.scrollX,t)})}},scrollLeft:function(t){if(this.length){var n="scrollLeft"in this[0];return t===e?n?this[0].scrollLeft:this[0].pageXOffset:this.each(n?function(){this.scrollLeft=t}:function(){this.scrollTo(t,this.scrollY)})}},position:function(){if(this.length){var t=this[0],e=this.offsetParent(),n=this.offset(),i=g.test(e[0].nodeName)?{top:0,left:0}:e.offset();return n.top-=parseFloat(r(t).css("margin-top"))||0,n.left-=parseFloat(r(t).css("margin-left"))||0,i.top+=parseFloat(r(e[0]).css("border-top-width"))||0,i.left+=parseFloat(r(e[0]).css("border-left-width"))||0,{top:n.top-i.top,left:n.left-i.left}}},offsetParent:function(){return this.map(function(){for(var t=this.offsetParent||f.body;t&&!g.test(t.nodeName)&&"static"==r(t).css("position");)t=t.offsetParent;return t})}},r.fn.detach=r.fn.remove,["width","height"].forEach(function(t){var n=t.replace(/./,function(t){return t[0].toUpperCase()});r.fn[t]=function(i){var o,s=this[0];return i===e?k(s)?s["inner"+n]:M(s)?s.documentElement["scroll"+n]:(o=this.offset())&&o[t]:this.each(function(e){s=r(this),s.css(t,Y(this,i,e,s[t]()))})}}),x.forEach(function(n,i){var o=i%2;r.fn[n]=function(){var n,a,s=r.map(arguments,function(t){var i=[];return n=$(t),"array"==n?(t.forEach(function(t){return t.nodeType!==e?i.push(t):r.zepto.isZ(t)?i=i.concat(t.get()):void(i=i.concat(N.fragment(t)))}),i):"object"==n||null==t?t:N.fragment(t)}),u=this.length>1;return s.length<1?this:this.each(function(e,n){a=o?n:n.parentNode,n=0==i?n.nextSibling:1==i?n.firstChild:2==i?n:null;var c=r.contains(f.documentElement,a);s.forEach(function(e){if(u)e=e.cloneNode(!0);else if(!a)return r(e).remove();a.insertBefore(e,n),c&&tt(e,function(e){if(!(null==e.nodeName||"SCRIPT"!==e.nodeName.toUpperCase()||e.type&&"text/javascript"!==e.type||e.src)){var n=e.ownerDocument?e.ownerDocument.defaultView:t;n.eval.call(n,e.innerHTML)}})})})},r.fn[o?n+"To":"insert"+(i?"Before":"After")]=function(t){return r(t)[n](this),this}}),N.Z.prototype=X.prototype=r.fn,N.uniq=P,N.deserializeValue=Q,r.zepto=N,r}();return t.Zepto=e,void 0===t.$&&(t.$=e),function(e){function h(t){return t._zid||(t._zid=n++)}function p(t,e,n,r){if(e=d(e),e.ns)var i=m(e.ns);return(a[h(t)]||[]).filter(function(t){return t&&(!e.e||t.e==e.e)&&(!e.ns||i.test(t.ns))&&(!n||h(t.fn)===h(n))&&(!r||t.sel==r)})}function d(t){var e=(""+t).split(".");return{e:e[0],ns:e.slice(1).sort().join(" ")}}function m(t){return new RegExp("(?:^| )"+t.replace(" "," .* ?")+"(?: |$)")}function g(t,e){return t.del&&!f&&t.e in c||!!e}function v(t){return l[t]||f&&c[t]||t}function y(t,n,i,o,s,u,f){var c=h(t),p=a[c]||(a[c]=[]);n.split(/\s/).forEach(function(n){if("ready"==n)return e(document).ready(i);var a=d(n);a.fn=i,a.sel=s,a.e in l&&(i=function(t){var n=t.relatedTarget;return!n||n!==this&&!e.contains(this,n)?a.fn.apply(this,arguments):void 0}),a.del=u;var c=u||i;a.proxy=function(e){if(e=T(e),!e.isImmediatePropagationStopped()){e.data=o;var n=c.apply(t,e._args==r?[e]:[e].concat(e._args));return n===!1&&(e.preventDefault(),e.stopPropagation()),n}},a.i=p.length,p.push(a),"addEventListener"in t&&t.addEventListener(v(a.e),a.proxy,g(a,f))})}function x(t,e,n,r,i){var o=h(t);(e||"").split(/\s/).forEach(function(e){p(t,e,n,r).forEach(function(e){delete a[o][e.i],"removeEventListener"in t&&t.removeEventListener(v(e.e),e.proxy,g(e,i))})})}function T(t,n){return(n||!t.isDefaultPrevented)&&(n||(n=t),e.each(w,function(e,r){var i=n[e];t[e]=function(){return this[r]=b,i&&i.apply(n,arguments)},t[r]=E}),t.timeStamp||(t.timeStamp=Date.now()),(n.defaultPrevented!==r?n.defaultPrevented:"returnValue"in n?n.returnValue===!1:n.getPreventDefault&&n.getPreventDefault())&&(t.isDefaultPrevented=b)),t}function S(t){var e,n={originalEvent:t};for(e in t)j.test(e)||t[e]===r||(n[e]=t[e]);return T(n,t)}var r,n=1,i=Array.prototype.slice,o=e.isFunction,s=function(t){return"string"==typeof t},a={},u={},f="onfocusin"in t,c={focus:"focusin",blur:"focusout"},l={mouseenter:"mouseover",mouseleave:"mouseout"};u.click=u.mousedown=u.mouseup=u.mousemove="MouseEvents",e.event={add:y,remove:x},e.proxy=function(t,n){var r=2 in arguments&&i.call(arguments,2);if(o(t)){var a=function(){return t.apply(n,r?r.concat(i.call(arguments)):arguments)};return a._zid=h(t),a}if(s(n))return r?(r.unshift(t[n],t),e.proxy.apply(null,r)):e.proxy(t[n],t);throw new TypeError("expected function")},e.fn.bind=function(t,e,n){return this.on(t,e,n)},e.fn.unbind=function(t,e){return this.off(t,e)},e.fn.one=function(t,e,n,r){return this.on(t,e,n,r,1)};var b=function(){return!0},E=function(){return!1},j=/^([A-Z]|returnValue$|layer[XY]$|webkitMovement[XY]$)/,w={preventDefault:"isDefaultPrevented",stopImmediatePropagation:"isImmediatePropagationStopped",stopPropagation:"isPropagationStopped"};e.fn.delegate=function(t,e,n){return this.on(e,t,n)},e.fn.undelegate=function(t,e,n){return this.off(e,t,n)},e.fn.live=function(t,n){return e(document.body).delegate(this.selector,t,n),this},e.fn.die=function(t,n){return e(document.body).undelegate(this.selector,t,n),this},e.fn.on=function(t,n,a,u,f){var c,l,h=this;return t&&!s(t)?(e.each(t,function(t,e){h.on(t,n,a,e,f)}),h):(s(n)||o(u)||u===!1||(u=a,a=n,n=r),(u===r||a===!1)&&(u=a,a=r),u===!1&&(u=E),h.each(function(r,o){f&&(c=function(t){return x(o,t.type,u),u.apply(this,arguments)}),n&&(l=function(t){var r,s=e(t.target).closest(n,o).get(0);return s&&s!==o?(r=e.extend(S(t),{currentTarget:s,liveFired:o}),(c||u).apply(s,[r].concat(i.call(arguments,1)))):void 0}),y(o,t,u,a,n,l||c)}))},e.fn.off=function(t,n,i){var a=this;return t&&!s(t)?(e.each(t,function(t,e){a.off(t,n,e)}),a):(s(n)||o(i)||i===!1||(i=n,n=r),i===!1&&(i=E),a.each(function(){x(this,t,i,n)}))},e.fn.trigger=function(t,n){return t=s(t)||e.isPlainObject(t)?e.Event(t):T(t),t._args=n,this.each(function(){t.type in c&&"function"==typeof this[t.type]?this[t.type]():"dispatchEvent"in this?this.dispatchEvent(t):e(this).triggerHandler(t,n)})},e.fn.triggerHandler=function(t,n){var r,i;return this.each(function(o,a){r=S(s(t)?e.Event(t):t),r._args=n,r.target=a,e.each(p(a,t.type||t),function(t,e){return i=e.proxy(r),r.isImmediatePropagationStopped()?!1:void 0})}),i},"focusin focusout focus blur load resize scroll unload click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select keydown keypress keyup error".split(" ").forEach(function(t){e.fn[t]=function(e){return 0 in arguments?this.bind(t,e):this.trigger(t)}}),e.Event=function(t,e){s(t)||(e=t,t=e.type);var n=document.createEvent(u[t]||"Events"),r=!0;if(e)for(var i in e)"bubbles"==i?r=!!e[i]:n[i]=e[i];return n.initEvent(t,r,!0),T(n)}}(e),function(e){function p(t,n,r){var i=e.Event(n);return e(t).trigger(i,r),!i.isDefaultPrevented()}function d(t,e,n,i){return t.global?p(e||r,n,i):void 0}function m(t){t.global&&0===e.active++&&d(t,null,"ajaxStart")}function g(t){t.global&&!--e.active&&d(t,null,"ajaxStop")}function v(t,e){var n=e.context;return e.beforeSend.call(n,t,e)===!1||d(e,n,"ajaxBeforeSend",[t,e])===!1?!1:void d(e,n,"ajaxSend",[t,e])}function y(t,e,n,r){var i=n.context,o="success";n.success.call(i,t,o,e),r&&r.resolveWith(i,[t,o,e]),d(n,i,"ajaxSuccess",[e,n,t]),b(o,e,n)}function x(t,e,n,r,i){var o=r.context;r.error.call(o,n,e,t),i&&i.rejectWith(o,[n,e,t]),d(r,o,"ajaxError",[n,r,t||e]),b(e,n,r)}function b(t,e,n){var r=n.context;n.complete.call(r,e,t),d(n,r,"ajaxComplete",[e,n]),g(n)}function E(t,e,n){if(n.dataFilter==j)return t;var r=n.context;return n.dataFilter.call(r,t,e)}function j(){}function w(t){return t&&(t=t.split(";",2)[0]),t&&(t==c?"html":t==f?"json":a.test(t)?"script":u.test(t)&&"xml")||"text"}function T(t,e){return""==e?t:(t+"&"+e).replace(/[&?]{1,2}/,"?")}function S(t){t.processData&&t.data&&"string"!=e.type(t.data)&&(t.data=e.param(t.data,t.traditional)),!t.data||t.type&&"GET"!=t.type.toUpperCase()&&"jsonp"!=t.dataType||(t.url=T(t.url,t.data),t.data=void 0)}function C(t,n,r,i){return e.isFunction(n)&&(i=r,r=n,n=void 0),e.isFunction(r)||(i=r,r=void 0),{url:t,data:n,success:r,dataType:i}}function O(t,n,r,i){var o,s=e.isArray(n),a=e.isPlainObject(n);e.each(n,function(n,u){o=e.type(u),i&&(n=r?i:i+"["+(a||"object"==o||"array"==o?n:"")+"]"),!i&&s?t.add(u.name,u.value):"array"==o||!r&&"object"==o?O(t,u,r,n):t.add(n,u)})}var i,o,n=+new Date,r=t.document,s=/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,a=/^(?:text|application)\/javascript/i,u=/^(?:text|application)\/xml/i,f="application/json",c="text/html",l=/^\s*$/,h=r.createElement("a");h.href=t.location.href,e.active=0,e.ajaxJSONP=function(i,o){if(!("type"in i))return e.ajax(i);var c,p,s=i.jsonpCallback,a=(e.isFunction(s)?s():s)||"Zepto"+n++,u=r.createElement("script"),f=t[a],l=function(t){e(u).triggerHandler("error",t||"abort")},h={abort:l};return o&&o.promise(h),e(u).on("load error",function(n,r){clearTimeout(p),e(u).off().remove(),"error"!=n.type&&c?y(c[0],h,i,o):x(null,r||"error",h,i,o),t[a]=f,c&&e.isFunction(f)&&f(c[0]),f=c=void 0}),v(h,i)===!1?(l("abort"),h):(t[a]=function(){c=arguments},u.src=i.url.replace(/\?(.+)=\?/,"?$1="+a),r.head.appendChild(u),i.timeout>0&&(p=setTimeout(function(){l("timeout")},i.timeout)),h)},e.ajaxSettings={type:"GET",beforeSend:j,success:j,error:j,complete:j,context:null,global:!0,xhr:function(){return new t.XMLHttpRequest},accepts:{script:"text/javascript, application/javascript, application/x-javascript",json:f,xml:"application/xml, text/xml",html:c,text:"text/plain"},crossDomain:!1,timeout:0,processData:!0,cache:!0,dataFilter:j},e.ajax=function(n){var u,f,s=e.extend({},n||{}),a=e.Deferred&&e.Deferred();for(i in e.ajaxSettings)void 0===s[i]&&(s[i]=e.ajaxSettings[i]);m(s),s.crossDomain||(u=r.createElement("a"),u.href=s.url,u.href=u.href,s.crossDomain=h.protocol+"//"+h.host!=u.protocol+"//"+u.host),s.url||(s.url=t.location.toString()),(f=s.url.indexOf("#"))>-1&&(s.url=s.url.slice(0,f)),S(s);var c=s.dataType,p=/\?.+=\?/.test(s.url);if(p&&(c="jsonp"),s.cache!==!1&&(n&&n.cache===!0||"script"!=c&&"jsonp"!=c)||(s.url=T(s.url,"_="+Date.now())),"jsonp"==c)return p||(s.url=T(s.url,s.jsonp?s.jsonp+"=?":s.jsonp===!1?"":"callback=?")),e.ajaxJSONP(s,a);var P,d=s.accepts[c],g={},b=function(t,e){g[t.toLowerCase()]=[t,e]},C=/^([\w-]+:)\/\//.test(s.url)?RegExp.$1:t.location.protocol,N=s.xhr(),O=N.setRequestHeader;if(a&&a.promise(N),s.crossDomain||b("X-Requested-With","XMLHttpRequest"),b("Accept",d||"*/*"),(d=s.mimeType||d)&&(d.indexOf(",")>-1&&(d=d.split(",",2)[0]),N.overrideMimeType&&N.overrideMimeType(d)),(s.contentType||s.contentType!==!1&&s.data&&"GET"!=s.type.toUpperCase())&&b("Content-Type",s.contentType||"application/x-www-form-urlencoded"),s.headers)for(o in s.headers)b(o,s.headers[o]);if(N.setRequestHeader=b,N.onreadystatechange=function(){if(4==N.readyState){N.onreadystatechange=j,clearTimeout(P);var t,n=!1;if(N.status>=200&&N.status<300||304==N.status||0==N.status&&"file:"==C){if(c=c||w(s.mimeType||N.getResponseHeader("content-type")),"arraybuffer"==N.responseType||"blob"==N.responseType)t=N.response;else{t=N.responseText;try{t=E(t,c,s),"script"==c?(1,eval)(t):"xml"==c?t=N.responseXML:"json"==c&&(t=l.test(t)?null:e.parseJSON(t))}catch(r){n=r}if(n)return x(n,"parsererror",N,s,a)}y(t,N,s,a)}else x(N.statusText||null,N.status?"error":"abort",N,s,a)}},v(N,s)===!1)return N.abort(),x(null,"abort",N,s,a),N;var A="async"in s?s.async:!0;if(N.open(s.type,s.url,A,s.username,s.password),s.xhrFields)for(o in s.xhrFields)N[o]=s.xhrFields[o];for(o in g)O.apply(N,g[o]);return s.timeout>0&&(P=setTimeout(function(){N.onreadystatechange=j,N.abort(),x(null,"timeout",N,s,a)},s.timeout)),N.send(s.data?s.data:null),N},e.get=function(){return e.ajax(C.apply(null,arguments))},e.post=function(){var t=C.apply(null,arguments);return t.type="POST",e.ajax(t)},e.getJSON=function(){var t=C.apply(null,arguments);return t.dataType="json",e.ajax(t)},e.fn.load=function(t,n,r){if(!this.length)return this;var a,i=this,o=t.split(/\s/),u=C(t,n,r),f=u.success;return o.length>1&&(u.url=o[0],a=o[1]),u.success=function(t){i.html(a?e("<div>").html(t.replace(s,"")).find(a):t),f&&f.apply(i,arguments)},e.ajax(u),this};var N=encodeURIComponent;e.param=function(t,n){var r=[];return r.add=function(t,n){e.isFunction(n)&&(n=n()),null==n&&(n=""),this.push(N(t)+"="+N(n))},O(r,t,n),r.join("&").replace(/%20/g,"+")}}(e),function(t){t.fn.serializeArray=function(){var e,n,r=[],i=function(t){return t.forEach?t.forEach(i):void r.push({name:e,value:t})};return this[0]&&t.each(this[0].elements,function(r,o){n=o.type,e=o.name,e&&"fieldset"!=o.nodeName.toLowerCase()&&!o.disabled&&"submit"!=n&&"reset"!=n&&"button"!=n&&"file"!=n&&("radio"!=n&&"checkbox"!=n||o.checked)&&i(t(o).val())}),r},t.fn.serialize=function(){var t=[];return this.serializeArray().forEach(function(e){t.push(encodeURIComponent(e.name)+"="+encodeURIComponent(e.value))}),t.join("&")},t.fn.submit=function(e){if(0 in arguments)this.bind("submit",e);else if(this.length){var n=t.Event("submit");this.eq(0).trigger(n),n.isDefaultPrevented()||this.get(0).submit()}return this}}(e),function(){try{getComputedStyle(void 0)}catch(e){var n=getComputedStyle;t.getComputedStyle=function(t,e){try{return n(t,e)}catch(r){return null}}}}(),e});
		!function(a,b){function r(a){return a.replace(/([A-Z])/g,"-$1").toLowerCase()}function s(a){return d?d+a:a.toLowerCase()}var d,h,i,j,k,l,m,n,o,p,c="",e={Webkit:"webkit",Moz:"",O:"o"},f=document.createElement("div"),g=/^((translate|rotate|scale)(X|Y|Z|3d)?|matrix(3d)?|perspective|skew(X|Y)?)$/i,q={};f.style.transform===b&&a.each(e,function(a,e){if(f.style[a+"TransitionProperty"]!==b)return c="-"+a.toLowerCase()+"-",d=e,!1}),h=c+"transform",q[i=c+"transition-property"]=q[j=c+"transition-duration"]=q[l=c+"transition-delay"]=q[k=c+"transition-timing-function"]=q[m=c+"animation-name"]=q[n=c+"animation-duration"]=q[p=c+"animation-delay"]=q[o=c+"animation-timing-function"]="",a.fx={off:d===b&&f.style.transitionProperty===b,speeds:{_default:400,fast:200,slow:600},cssPrefix:c,transitionEnd:s("TransitionEnd"),animationEnd:s("AnimationEnd")},a.fn.animate=function(c,d,e,f,g){return a.isFunction(d)&&(f=d,e=b,d=b),a.isFunction(e)&&(f=e,e=b),a.isPlainObject(d)&&(e=d.easing,f=d.complete,g=d.delay,d=d.duration),d&&(d=("number"==typeof d?d:a.fx.speeds[d]||a.fx.speeds._default)/1e3),g&&(g=parseFloat(g)/1e3),this.anim(c,d,e,f,g)},a.fn.anim=function(c,d,e,f,s){var t,v,y,u={},w="",x=this,z=a.fx.transitionEnd,A=!1;if(d===b&&(d=a.fx.speeds._default/1e3),s===b&&(s=0),a.fx.off&&(d=0),"string"==typeof c)u[m]=c,u[n]=d+"s",u[p]=s+"s",u[o]=e||"linear",z=a.fx.animationEnd;else{v=[];for(t in c)g.test(t)?w+=t+"("+c[t]+") ":(u[t]=c[t],v.push(r(t)));w&&(u[h]=w,v.push(h)),d>0&&"object"==typeof c&&(u[i]=v.join(", "),u[j]=d+"s",u[l]=s+"s",u[k]=e||"linear")}return y=function(b){if("undefined"!=typeof b){if(b.target!==b.currentTarget)return;a(b.target).unbind(z,y)}else a(this).unbind(z,y);A=!0,a(this).css(q),f&&f.call(this)},d>0&&(this.bind(z,y),setTimeout(function(){A||y.call(x)},1e3*(d+s)+25)),this.size()&&this.get(0).clientLeft,this.css(u),d<=0&&setTimeout(function(){x.each(function(){y.call(this)})},0),this},f=null}(Zepto);
		!function(a,b){function h(c,d,e,f,g){"function"!=typeof d||g||(g=d,d=b);var h={opacity:e};return f&&(h.scale=f,c.css(a.fx.cssPrefix+"transform-origin","0 0")),c.animate(h,d,null,g)}function i(b,c,d,e){return h(b,c,0,d,function(){f.call(a(this)),e&&e.call(this)})}var c=window.document,e=(c.documentElement,a.fn.show),f=a.fn.hide,g=a.fn.toggle;a.fn.show=function(a,c){return e.call(this),a===b?a=0:this.css("opacity",0),h(this,a,1,"1,1",c)},a.fn.hide=function(a,c){return a===b?f.call(this):i(this,a,"0,0",c)},a.fn.toggle=function(c,d){return c===b||"boolean"==typeof c?g.call(this,c):this.each(function(){var b=a(this);b["none"==b.css("display")?"show":"hide"](c,d)})},a.fn.fadeTo=function(a,b,c){return h(this,a,b,null,c)},a.fn.fadeIn=function(a,b){var c=this.css("opacity");return c>0?this.css("opacity",0):c=1,e.call(this).fadeTo(a,c,b)},a.fn.fadeOut=function(a,b){return i(this,a,null,b)},a.fn.fadeToggle=function(b,c){return this.each(function(){var d=a(this);d[0==d.css("opacity")||"none"==d.css("display")?"fadeIn":"fadeOut"](b,c)})}}(Zepto);

		var tests_data = JSON.parse('<?php echo json_encode(Spry::config()->tests);?>');

        var submitted_tests = {
            'completed': {},
            'last_id': '',
            'last_body': ''
        };

		function update_json()
		{
            if(typeof(tests_data[$('#api-request-test').val()]) !== 'undefined')
            {
                $('#api-request-data').val(JSON.stringify(tests_data[$('#api-request-test').val()]['params'], null, "\t"));
            }
            else
            {
                $('#api-request-data').val('');
            }
		}

		function update_logs($type)
		{
			if($type == 'php' || $type == 'all')
			{
				$.post('<?php echo $_SERVER['REQUEST_URI'];?>', { ajax: 'php_logs' }, function(response){
					$('.php-logs').val(response);
					$('.php-logs').each(function(){
						$(this).scrollTop($(this)[0].scrollHeight);
					});
				});
			}

			if($type == 'api' || $type == 'all')
			{
				$.post('<?php echo $_SERVER['REQUEST_URI'];?>', { ajax: 'api_logs' }, function(response){
					$('.api-logs').val(response);
					$('.api-logs').each(function(){
						$(this).scrollTop($(this)[0].scrollHeight);
					});
				});
			}
		}

		function update_logs_scrolled()
		{
			$('.php-logs,.api-logs').each(function(){
				$(this).scrollTop($(this)[0].scrollHeight);
			});
		}

        function submit_test(route, params, t_id, expect, tests)
        {
            submitted_tests['last_response'] = '';

            submitted_tests['completed'][t_id] = '';

            $.post(route, params, function(response){
                if(response)
                {
                    submitted_tests['last_response'] = response;

                    var result = {
                        'status': 'Failed',
                        'params': JSON.parse(params),
                        'expect': expect,
                        'result': {},
                        'full_response': response
                    };

                    if(typeof(expect) !== 'undefined' && !$.isEmptyObject(expect))
                    {
                        result['status'] = 'Passed';

                        for(var e in expect)
                        {
                            result['result'][e] = response[e];

                            if(response[e] !== expect[e])
                            {
                                result['status'] = 'Failed';
                            }
                        }
                    }

                    submitted_tests['completed'][t_id] = result;

                    track_submitted_tests(tests);
                }
            }, 'json');
        }

        function track_submitted_tests(tests)
        {
            var p, t_id, c, pv;
            var param;
            var param_value;
            var path;
            var response_code = 2050;

            for(t_id in tests_data)
            {
                if(tests === 'All' || tests === t_id)
                {
                    if(typeof(submitted_tests['completed'][t_id]) === 'undefined')
                    {
                        var route = '<?php echo Spry::config()->endpoint;?>' + tests_data[t_id]['route'];
                        var params = tests_data[t_id]['params'];
                        var expect = tests_data[t_id]['expect'];

                        if(typeof(expect) === 'object')
                        {
                            for(p in params)
                            {
                                param = params[p].toString();

                                if(submitted_tests['last_response'] && param.substr(0, 1) === '{' && param.substr(-1, 1) === '}')
                                {
                                    path = param.substr(1, (param.length - 2)).split('.');
                                    param_value = submitted_tests['last_response'];

                                    for(pv in path)
                                    {
                                        if(typeof(param_value[path[pv]]) !== 'undefined')
                                        {
                                            param_value = param_value[path[pv]];
                                        }
                                        else
                                        {
                                            param_value = null;
                                            break;
                                        }
                                    }

                                    params[p] = param_value;
                                }
                            }

                            submit_test(route, JSON.stringify(params), t_id, expect, tests);
                            return;
                        }
                    }
                }
            }

            // console.log(submitted_tests['completed']);

            for(c in submitted_tests['completed'])
            {
                if(submitted_tests['completed'][c]['status'] !== 'Passed')
                {
                    response_code = 5050;
                }
            }

            var data = {
                ajax: 'build_tests_response',
                'response_code': response_code,
                'results': submitted_tests['completed']
            };

            // Completed
            $.post('<?php echo $_SERVER['REQUEST_URI'];?>', data, function(response){
                update_test_response(response);
            });

        }

        function update_test_response(response)
        {
            if(response && response.indexOf('{') > -1)
            {
                var data = JSON.parse(response);

                if(typeof(data['response']) !== 'undefined')
                {
                    if(data['response'] === 'success')
                    {
                        $('#api-request-legend').append('<span class="status success">Success</span>');
                    }

                    if(data['response'] === 'error')
                    {
                        $('#api-request-legend').append('<span class="status error">Error</span>');
                    }

                    if(data['response'] === 'unknown')
                    {
                        $('#api-request-legend').append('<span class="status unknown">Unknown</span>');
                    }

                    $('#api-request-response textarea').val(JSON.stringify(JSON.parse(response), null, "\t"));
                }
                else
                {
                    $('#api-request-legend').append('<span class="status unknown">Unknown</span>');
                    $('#api-request-response textarea').val(response);
                }
            }
            else
            {
                $('#api-request-legend').append('<span class="status unknown">Unknown</span>');
                $('#api-request-response textarea').val(response);
            }

            $('#api-request-legend .loader').remove();

            update_logs('all');
        }

		$(document).ready(function(){
			$('.tabs li').on('click', function(){
				var tab = $(this).attr('data-tab');
				$('.tabs li').removeClass('active');
				$(this).addClass('active');
				$('.tab-content').each(function(){
					var content = $(this);
					if(content.attr('data-tab') !== tab)
					{
						content.fadeOut(50);
					}
					if(content.attr('data-tab') === tab && content.css('display') === 'none')
					{
						setTimeout(function(){
							content.fadeIn(50, function(){
								update_logs_scrolled();
							});
						}, 100);
					}
				});
			});

			$('#hash-input').on('keyup change', function() {
				$.post('<?php echo $_SERVER['REQUEST_URI'];?>', { ajax: 'hash', hash: $(this).val() }, function(response){
					$('#hash-value').html(response);
				});
			});

			$('#api-request-submit').on('click', function() {
				$('#api-request-legend span').remove();
				$('#api-request-legend').append('<span class="loader" style="display:none"></span>');
				$('#api-request-response textarea').val('');
				$('#api-request-legend .loader').fadeIn(100);

                submitted_tests['completed'] = {};

                $.post('<?php echo $_SERVER['REQUEST_URI'];?>', { ajax: 'get_tests' }, function(response){
                    if(response)
                    {
                        data = JSON.parse(response);
                        if(typeof(data.body) !== 'undefined')
                        {
                            tests_data = data.body;

                            var test_id = $('#api-request-test').val();

                            if(test_id == 'All Tests')
                            {
                                track_submitted_tests('All');
                            }
                            else
                            {
                                track_submitted_tests(test_id);
                            }
                        }
                    }
                });
			});

			$('#db-migrate-submit').on('click', function(){

				var confirmed = true;

				if(!$('#dryrun').is(":checked"))
				{
					confirmed = confirm('Are you sure you want to Run DB Migrate'+($('#destructive').is(":checked") ? ' with Destructive turned ON' : '')+'?');
				}

				if(confirmed)
				{
					$('#db-migrate-container legend span').remove();
					$('#db-migrate-container legend').append('<span class="loader" style="display:none"></span>');
					$('#db-migrate-container textarea').val('');
					$('#db-migrate-container legend .loader').fadeIn(100);
					$.post('<?php echo $_SERVER['REQUEST_URI'];?>', { ajax: 'db_migrate', destructive: ($('#destructive').is(":checked") ? 1 : 0), dryrun: ($('#dryrun').is(":checked") ? 1 : 0) }, function(response){

						if(response && response.indexOf('{') > -1)
						{
							var data = JSON.parse(response);

							if(typeof(data['response']) !== 'undefined')
							{
								if(data['response'] === 'success')
								{
									$('#db-migrate-container legend').append('<span class="status success">Success</span>');
								}

								if(data['response'] === 'error')
								{
									$('#db-migrate-container legend').append('<span class="status error">Error</span>');
								}

								if(data['response'] === 'unknown')
								{
									$('#db-migrate-container legend').append('<span class="status unknown">Unknown</span>');
								}

								$('#db-migrate-container textarea').val(JSON.stringify(JSON.parse(response), null, "\t"));
							}
							else
							{
								$('#db-migrate-container legend').append('<span class="status unknown">Unknown</span>');
								$('#db-migrate-container textarea').val(response);
							}
						}
						else
						{
							$('#db-migrate-container legend').append('<span class="status unknown">Unknown</span>');
							$('#db-migrate-container textarea').val(response);
						}

						$('#db-migrate-container legend .loader').remove();

						update_logs('all');

					});
				}
			});

			$('.clear-php-logs').on('click', function(){
				if(confirm('Are you Sure?'))
				{
					$.post('<?php echo $_SERVER['REQUEST_URI'];?>', { ajax: 'clear_php_logs' }, function(response){
						update_logs('php');
					});
				}
			});

			$('.clear-api-logs').on('click', function(){
				if(confirm('Are you Sure?'))
				{
					$.post('<?php echo $_SERVER['REQUEST_URI'];?>', { ajax: 'clear_api_logs' }, function(response){
						update_logs('api');
					});
				}
			});

			update_logs('all');

		});



		</script>


		<ul class="tabs">
			<li class="active" data-tab="tester">Tester</li>
			<li data-tab="tools">Tools</li>
			<li data-tab="php-logs">PHP Logs</li>
			<li data-tab="api-logs">API Logs</li>
		</ul>


		<div class="tab-content" data-tab="tester">
			<div>
				<div class="top-section">
					<div class="left-section">
						<fieldset class="api-form">
							<legend>Api Request</legend>
							<div class="content">
								<span class="select">
									<select id="api-request-test" onchange="update_json();">
                                        <option value="All Tests">All Tests</option>
										<?php foreach (Spry::config()->tests as $test_id => $test) { ?>
											<option value="<?php echo $test_id;?>"><?php echo $test_id.(!empty($test['title']) ? ' - '.$test['title'] : '');?></option>
										<?php } ?>
									</select>
								</span>
								<span class="submit">
									<button id="api-request-submit">Submit</button>
								</span>
								<div class="api-request-text-container">
									<textarea id="api-request-data" style="padding:10px;width: 100%;">Run All Tests</textarea>
								</div>
							</div>
						</fieldset>
					</div>

					<div class="right-section">
						<fieldset>
							<legend id="api-request-legend">Api Response</legend>
							<div id="api-request-response" class="content">
							<textarea></textarea>
							</div>
						</fieldset>
					</div>
				</div>

				<div class="bottom-section">
					<div class="left-section">
						<fieldset class="php-logs">
							<legend>PHP Logs &nbsp;-&nbsp; <button class="clear-php-logs">clear</button></legend>
							<div class="content">
								<textarea class="php-logs"></textarea>
							</div>
							<script>
								$('php-logs .content').scrollTop = $('php-logs .content').scrollHeight;
							</script>
						</fieldset>
					</div>
					<div class="right-section">
						<fieldset class="api-logs">
							<legend>API Logs &nbsp;-&nbsp; <button class="clear-api-logs">clear</button></legend>
							<div class="content">
								<textarea class="api-logs"></textarea>
							</div>
							<script>
								$('api-logs .content').scrollTop = $('api-logs .content').scrollHeight;
							</script>
						</fieldset>
					</div>
				</div>
			</div>
		</div>

		<div class="tab-content" data-tab="tools">

			<div class="left-section">
				<fieldset>
					<legend>Tools</legend>
					<div class="content">
						<h3>Get Hash Value</h3>
						<input id="hash-input" type="text" name="hash" value="" autocomplete="off"> = <span id="hash-value"></span>
					</div>
				</fieldset>
			</div>

			<div class="right-section" id="db-migrate-container">
				<fieldset>
					<legend>DB Migrate</legend>
					<div class="content">
						<div class="migrate-controls">

							<label title="Destructive will drop tables and columns that are not listed in your Migrate Schema. Which will result in Lost Data.">
								<input type="checkbox" id="destructive" value="1">Destructive
							</label>
							<label title="Dryrun will only report back what the migrate will do, but not make any actual changes.">
								<input type="checkbox" id="dryrun" value="1" checked="checked">Dryrun
							</label>
							<span class="submit">
								<button id="db-migrate-submit">Submit</button>
							</span>

						</div>
						<textarea></textarea>
					</div>
				</fieldset>
			</div>

		</div>

		<div class="tab-content" data-tab="php-logs">
			<fieldset>
				<legend>PHP Logs &nbsp;-&nbsp; <button class="clear-php-logs">clear</button></legend>
				<div class="content">
					<textarea class="php-logs"></textarea>
				</div>
			</fieldset>
		</div>

		<div class="tab-content" data-tab="api-logs">
			<fieldset>
				<legend>Api Logs &nbsp;-&nbsp; <button class="clear-api-logs">clear</button></legend>
				<div class="content">
					<textarea class="api-logs"></textarea>
				</div>
			</fieldset>
		</div>

		<?php

		exit;
	}
}
