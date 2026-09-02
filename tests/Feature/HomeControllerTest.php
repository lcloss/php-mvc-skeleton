<?php
use app\controllers\HomeController;

it('renderiza a home com layout', function () {
  $c = new HomeController();
  ob_start();
  $c->index();
  $out = ob_get_clean();
  expect($out)->toContain(env('APP_NAME'));
});
