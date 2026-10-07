<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$views = [
    ['billing.index', ['billingMode'=>'manage','canManageBilling'=>true]],
    ['billing.index', ['billingMode'=>'return','canManageBilling'=>true]],
    ['billing.index', ['billingMode'=>'access','canManageBilling'=>true]],
    ['billing.index', ['billingMode'=>'manage','canManageBilling'=>false]],
    ['billing.restricted', ['canManageBilling'=>true]],
    ['billing.restricted', ['canManageBilling'=>false]],
];
foreach ($views as [$view, $data]) {
    $html = view($view,$data)->render();
    $dom = new DOMDocument(); libxml_use_internal_errors(true); $dom->loadHTML('<?xml encoding="UTF-8">'.$html); $xpath=new DOMXPath($dom);$ids=[];
    foreach($xpath->query('//*[@id]') as $node){$id=$node->getAttribute('id');if(isset($ids[$id]))throw new RuntimeException('Duplicate ID '.$id);$ids[$id]=true;}
    foreach($xpath->query('//*[@aria-controls or @aria-describedby or @aria-labelledby]') as $node)foreach(['aria-controls','aria-describedby','aria-labelledby'] as $attribute)foreach(preg_split('/\s+/',trim($node->getAttribute($attribute))) as $id)if($id!==''&&!isset($ids[$id]))throw new RuntimeException('Broken ARIA '.$id);
    if($xpath->query('//main')->length!==1||$xpath->query('//body/header')->length!==1||$xpath->query('//body/footer')->length!==1||str_contains($html,'href="#"'))throw new RuntimeException('Invalid landmarks or links');
    if(!$data['canManageBilling']&&str_contains($html,'data-billing-form'))throw new RuntimeException('Readonly receives owner form');
    echo $view.' '.($data['billingMode']??'restricted').' '.($data['canManageBilling']?'owner':'readonly').': render/landmarks/IDs/ARIA OK'.PHP_EOL;
}
