<?php
/*********************************************************************
    http.php

    HTTP controller for the osTicket API

    Jared Hancock
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require 'api.inc.php';
# Include the main api urls
require_once INCLUDE_DIR."class.dispatcher.php";
$dispatcher = patterns('',
        url_get("^/tickets$", array('api.tickets.php:TicketApiController','listTickets')),
        url_post("^/tickets\.(?P<format>xml|json|email)$", array('api.tickets.php:TicketApiController','create')),
        url_get("^/tickets/(?P<id>[A-Za-z0-9\-]+)\.(?P<format>xml|json)$", array('api.tickets.php:TicketApiController','read')),
        url_post("^/tickets/(?P<id>[A-Za-z0-9\-]+)\.(?P<format>xml|json)$", array('api.tickets.php:TicketApiController','update')),
        url_post("^/tickets/(?P<id>[A-Za-z0-9\-]+)/reply\.(?P<format>xml|json)$", array('api.tickets.php:TicketApiController','postReply')),
        url_post("^/tickets/(?P<id>[A-Za-z0-9\-]+)/note\.(?P<format>xml|json)$", array('api.tickets.php:TicketApiController','postNote')),
        url_post("^/tickets/(?P<id>[A-Za-z0-9\-]+)/merge\.(?P<format>xml|json)$", array('api.tickets.php:TicketApiController','postMerge')),
        url('^/tasks/', patterns('',
                url_post("^cron$", array('api.cron.php:CronApiController', 'execute'))
         )),
        // KB (Knowledge Base) API endpoints
        url('^/kb/', patterns('',
            // FAQ endpoints
            url_get("^faqs\.(?P<format>json|xml)$", array('api.kb.php:KbApiController', 'listFaqs')),
            url_get("^faqs/(?P<id>\d+)\.(?P<format>json|xml)$", array('api.kb.php:KbApiController', 'readFaq')),
            url_post("^faqs\.(?P<format>json|xml)$", array('api.kb.php:KbApiController', 'createFaq')),
            url_post("^faqs/(?P<id>\d+)\.(?P<format>json|xml)$", array('api.kb.php:KbApiController', 'updateFaq')),
            url_delete("^faqs/(?P<id>\d+)\.(?P<format>json|xml)$", array('api.kb.php:KbApiController', 'deleteFaq')),
            // Category endpoints
            url_get("^categories\.(?P<format>json|xml)$", array('api.kb.php:KbApiController', 'listCategories')),
            url_get("^categories/(?P<id>\d+)\.(?P<format>json|xml)$", array('api.kb.php:KbApiController', 'readCategory')),
            url_post("^categories\.(?P<format>json|xml)$", array('api.kb.php:KbApiController', 'createCategory')),
            url_post("^categories/(?P<id>\d+)\.(?P<format>json|xml)$", array('api.kb.php:KbApiController', 'updateCategory')),
            url_delete("^categories/(?P<id>\d+)\.(?P<format>json|xml)$", array('api.kb.php:KbApiController', 'deleteCategory')),
        ))
    );

// Send api signal so backend can register endpoints
Signal::send('api', $dispatcher);
# Call the respective function
print $dispatcher->resolve(Osticket::get_path_info());
?>
