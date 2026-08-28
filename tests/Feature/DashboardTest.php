<?php

test('dashboard url is not available', function () {
    $this->get('/dashboard')->assertNotFound();
});
