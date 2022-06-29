<?php

include_once './base.php';

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;

$key = InMemory::plainText('d111b71edaed7a34b72c57380f4dc56a6152101a5ccc13443da5ac660351b53f');
// 对称加密
$config = Configuration::forSymmetricSigner(
// You may use any HMAC variations (256, 384, and 512)
    new Sha256(),
    // replace the value below with a key of your own!
    $key
// You may also override the JOSE encoder/decoder if needed by providing extra arguments here
);

// 签名
$now   = new DateTimeImmutable();
$token = $config->builder()
    // Configures the issuer (iss claim)
    ->issuedBy('weicheche.cn')
    // Configures the audience (aud claim)
    ->permittedFor('http://example.org')
    // Configures the id (jti claim)
    ->identifiedBy('4f1g23a12aa')
    // Configures the time that the token was issue (iat claim)
    ->issuedAt($now)
    ->canOnlyBeUsedAfter($now)
    // Configures the expiration time of the token (exp claim)
    ->expiresAt($now->modify('+1 hour'))
    // Configures a new claim, called "uid"
    ->withClaim('uid', 143)
    // Configures a new header, called "foo"
    ->withHeader('foo', 'bar')
    // Builds a new token
    ->getToken($config->signer(), $config->signingKey());

echo "token:",$token->toString(),"\n";
$encKey = "f68021d182fa03b49d307fc4c30664fe591682e2c0bc38da19deac5f48f7e621";
$ivlen = openssl_cipher_iv_length("AES-128-CBC");
$iv = openssl_random_pseudo_bytes($ivlen);
$enc = base64_encode(openssl_encrypt($token->toString(), 'AES-128-CBC', $encKey, OPENSSL_RAW_DATA, $iv));
echo "enc:", $enc,"\n";
$dec = openssl_decrypt(base64_decode($enc), "AES-128-CBC", $encKey, OPENSSL_RAW_DATA, $iv);
echo "dec:", $dec, "\n";

$tk = $config->parser()->parse($token->toString());
assert($tk instanceof UnencryptedToken);
$tk->claims()->all();


// 验证
$clock = new \Lcobucci\Clock\FrozenClock(new \DateTimeImmutable());
$config->setValidationConstraints(new SignedWith($config->signer(), $config->signingKey()));
$config->setValidationConstraints(new StrictValidAt($clock, \DateInterval::createFromDateString("60 seconds")));

$tokenStr = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiIsImZvbyI6ImJhciJ9.eyJpc3MiOiJ3ZWljaGVjaGUuY24iLCJhdWQiOiJodHRwOi8vZXhhbXBsZS5vcmciLCJqdGkiOiI0ZjFnMjNhMTJhYSIsImlhdCI6MTY1NjM4MTI1Mi41MzAwOTgsIm5iZiI6MTY1NjM4MTI1Mi41MzAwOTgsImV4cCI6MTY1NjM4NDg1Mi41MzAwOTgsInVpZCI6MTQzfQ.d0hI6mytqGPEsDEKhCZQVT4zf1apJF2W_-RLaiUmVhI";
if (!$config->validator()->validate($config->parser()->parse($tokenStr), ...$config->validationConstraints())) {
    echo "验证失败\n";
} else {
    echo "验证成功\n";
}


echo bin2hex(random_bytes(32));
//
//class YiyeAccount
//{
//    public $id;
//    public $amount;
//
//    public function __construct($id,$amt)
//    {
//        $this->id = $id;
//        $this->amount = $amt;
//    }
//}
//
//class StAccount
//{
//    public $id;
//    public $amount;
//
//    public function __construct($id,$amt)
//    {
//        $this->id = $id;
//        $this->amount = $amt;
//    }
//}
//
//$yiyeQueue = new SplPriorityQueue();
//$stQueue = new SplPriorityQueue();
//$yiyeQueue->setExtractFlags(SplPriorityQueue::EXTR_DATA);
//$stQueue->setExtractFlags(SplPriorityQueue::EXTR_DATA);
//
//$yiyeQueue->insert(new YiyeAccount(1, 20000), 20000);
//$yiyeQueue->insert(new YiyeAccount(2, 10000), 10000);
//$yiyeQueue->insert(new YiyeAccount(3,30000), 30000);
//$yiyeQueue->insert(new YiyeAccount(4,5000), 5000);
//
//$stQueue->insert(new StAccount(10,2000), 2000);
//$stQueue->insert(new StAccount(20,5000), 5000);
//$stQueue->insert(new StAccount(30,8000), 8000);
//$stQueue->insert(new StAccount(40,20000), 20000);
//$stQueue->insert(new StAccount(50,10000), 10000);
//
//while (!$stQueue->isEmpty() && !$yiyeQueue->isEmpty()) {
//    $stAct = $stQueue->extract();
//    $yiyeAct = $yiyeQueue->extract();
//
//    echo "yiyeact:",$yiyeAct->id,",amt:",$yiyeAct->amount,"stact:",$stAct->id,";stamt:",$stAct->amount,"\n";
//
//    // 扣款
//    if ($yiyeAct->amount >= $stAct->amount) {
//        // 钱够扣
//        // $rst = ['yiye_act_id', 'st_act_id', 'amt'];// amt 等于 $stAct->amount
//        $yiyeAct->amount -= $stAct->amount;
//        if ($yiyeAct->amount > 0) {
//            $yiyeQueue->insert($yiyeAct, $yiyeAct->amount);
//        }
//    } else {
//        // 不够扣
//        // $rst = ['yiye_act_id', 'st_act_id', 'amt'];// amt 等于 $yiyeAct->amount
//        $stAct->amount -= $yiyeAct->amount;
//        $stQueue->insert($stAct, $stAct->amount);
//    }
//}
