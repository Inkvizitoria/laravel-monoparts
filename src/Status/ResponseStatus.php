<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Status;

/**
 * Business-level response status derived from Monobank documentation
 * (states, sub-states, return status, availability, etc.).
 */
enum ResponseStatus: string
{
    case ORDER_SUCCESS = 'order_success';
    case ORDER_FAIL = 'order_fail';
    case ORDER_IN_PROCESS = 'order_in_process';
    case ORDER_CREATED = 'order_created';
    case ORDER_DUPLICATE = 'order_duplicate';

    case RETURN_OK = 'return_ok';
    case RETURN_ERROR = 'return_error';

    case CHECK_PAID_YES = 'check_paid_yes';
    case CHECK_PAID_NO = 'check_paid_no';

    case AVAILABLE = 'available';
    case NOT_AVAILABLE = 'not_available';

    case CLIENT_FOUND = 'client_found';
    case CLIENT_NOT_FOUND = 'client_not_found';

    case SUCCESS_HTTP = 'success_http';
    case CLIENT_ERROR_HTTP = 'client_error_http';
    case SERVER_ERROR_HTTP = 'server_error_http';
}
