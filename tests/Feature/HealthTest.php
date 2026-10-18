<?php

it('reports healthy on the /up endpoint used by deploy smoke tests', function () {
    $this->get('/up')->assertOk();
});
