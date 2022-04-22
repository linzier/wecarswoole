<?php

use WecarSwoole\Client\Response;

include_once './base.php';

$s = '{
	"details": {
		"0": {
			"oil_amt": 11.28,
			"order_amt": 8,
			"money_amt": 80,
			"money_wcc_amt": 80,
			"contract_discnt_amt": 0,
			"money_save_amt": 0
		},
		"92": {
			"oil_amt": 1.34,
			"order_amt": 1,
			"money_amt": 10,
			"money_wcc_amt": 10,
			"contract_discnt_amt": 0,
			"money_save_amt": 0
		},
		"CNG": {
			"oil_amt": 2.65,
			"order_amt": 1,
			"money_amt": 10,
			"money_wcc_amt": 10,
			"contract_discnt_amt": 0,
			"money_save_amt": 0
		}
	},
	"退款": {
		"0": {
			"oil_amt": 11.28,
			"order_amt": 8,
			"money_amt": -80,
			"money_wcc_amt": -80,
			"contract_discnt_amt": 0
		},
		"92": {
			"oil_amt": 1.34,
			"order_amt": 1,
			"money_amt": -10,
			"money_wcc_amt": -10,
			"contract_discnt_amt": 0
		},
		"CNG": {
			"oil_amt": 2.65,
			"order_amt": 1,
			"money_amt": -10,
			"money_wcc_amt": -10,
			"contract_discnt_amt": 0
		}
	},
	"summary": {
		"order_amt": 20,
		"oil_amt": 30.55,
		"money_amt": 0,
		"money_wcc_amt": 0,
		"money_save_amt": 0,
		"contract_discnt_amt": 0
	}
}';
$a = json_decode($s, true, 30, ~JSON_NUMERIC_CHECK);
var_export($a);