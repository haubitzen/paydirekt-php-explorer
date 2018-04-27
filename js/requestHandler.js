function ajaxRequest(event) {
    var ajaxRequest = new XMLHttpRequest();
    var req;
    var output = document.getElementById("output");
    ajaxRequest.addEventListener("load", ajaxLoad);
    ajaxRequest.addEventListener("error", ajaxError);
    switch(event.target.id) {
        case "clearSession":
            req = new FormData();
            req.append("action", "clearSession");
            break;
        case "postShippingAddress":
            req = new FormData(document.getElementById("shippingAddressForm"));
            req.append("action", "postShippingAddress");
            break;
        case "addItem":
            req = new FormData(document.getElementById("shoppingBasketForm"));
            req.append("action", "addItem");
            break;
        case "postTokenObtain":
            req = new FormData(document.getElementById("tokenObtainForm"));
            req.append("action", "postTokenObtain");
            break;
        case "postTokenObtainTp":
            req = new FormData(document.getElementById("tokenObtainForm"));
            req.append("action", "postTokenObtainTp");
            break;
        case "checkTokenExpiry":
            req = new FormData();
            req.append("action", "checkTokenExpiry");
            break;
        case "postCustomerAuthorization":
            req = new FormData(document.getElementById("postCustomerAuthorizationForm"));
            req.append("action", "postCustomerAuthorization");
            break;
        case "getCustomerAuthorization":
            req = new FormData(document.getElementById("getCustomerAuthorizationForm"));
            req.append("action", "getCustomerAuthorization");
            break;
        case "getMerchantAuthorization":
            req = new FormData(document.getElementById("getMerchantAuthorizationForm"));
            req.append("action", "getMerchantAuthorization");
            break;
        case "postCheckout":
            req = new FormData(document.getElementById("postCheckoutForm"));
            req.append("action", "postCheckout");
            break;
        case "executeCheckout":
            req = new FormData(document.getElementById("executeCheckoutForm"));
            req.append("action", "executeCheckout");
            break;
        case "getCheckout":
            req = new FormData(document.getElementById("getCheckoutForm"));
            req.append("action", "getCheckout");
            break;
        case "updateDeliveryInformation":
            req = new FormData(document.getElementById("updateDeliveryInformationForm"));
            req.append("action", "updateDeliveryInformation");
            break;
        case "updateInvoiceReference":
            req = new FormData(document.getElementById("updateInvoiceReferenceForm"));
            req.append("action", "updateInvoiceReference");
            break;
        case "postCloseOrder":
            req = new FormData(document.getElementById("closeOrderForm"));
            req.append("action", "closeOrder");
            break;
        case "postCapture":
            req = new FormData(document.getElementById("postCaptureForm"));
            req.append("action", "postCapture");
            break;
        case "getCapture":
            req = new FormData(document.getElementById("getCaptureForm"));
            req.append("action", "getCapture");
            break;
        case "postRefund":
            req = new FormData(document.getElementById("postRefundForm"));
            req.append("action", "postRefund");
            break;
        case "getRefund":
            req = new FormData(document.getElementById("getRefundForm"));
            req.append("action", "getRefund");
            break;
        case "getReport":
            req = new FormData(document.getElementById("getReportForm"));
            req.append("action", "getReport");
        break;
        case "getAccountsStatus":
            req = new FormData(document.getElementById("getAccountsForm"));
            req.append("action", "getAccountsStatus");
        break;
        case "getAccountsCreditSum":
            req = new FormData(document.getElementById("getAccountsForm"));
            req.append("action", "getAccountsCreditSum");
        break;
        case "getAccountsTransactions":
            req = new FormData(document.getElementById("getAccountsForm"));
            req.append("action", "getAccountsTransactions");
        break;
        case "logMenu":
            req = new FormData();
            req.append("action", "getLog");
        break;
        case "eraseLog":
            req = new FormData();
            req.append("action", "eraseLog");
        break;
        default:
            alert("Nicht definiert");
        }
    ajaxRequest.open("post", "api/api_explorer.php");
    ajaxRequest.send(req);
}
                
function ajaxLoad(event) {
    var output = document.getElementById("output");
    //ajaxResponse = event.target.responseText;
    var ajaxResponse = checkIfJson(event.target.responseText);
    console.log(ajaxResponse);
    if (ajaxResponse.redirect) {
        console.log("redirecting...");
    } else if (ajaxResponse.notify) {
        output.innerHTML = ajaxResponse.notify;
    } else {
        output.innerHTML = ajaxResponse;
    }
    if (ajaxResponse.redirect) {
        window.location.href = ajaxResponse.redirect;
    }
}

function ajaxError(event) {
    output.innerHTML = event.target.statusText;
}

function checkIfJson(string) {
    try {
        JSON.parse(string);
    } catch (error) {
        return string;
    }
    return JSON.parse(string);
    //return JSON.stringify(JSON.parse(string),null,2);
}