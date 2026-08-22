<?php

return [

    /*
     * Maximum rows returned per scope per sync request. Clients repeat the
     * request with the returned cursor until has_more is false.
     */
    'page_size' => (int) env('SYNC_PAGE_SIZE', 500),

];
