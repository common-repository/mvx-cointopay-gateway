(function() {
  var data = cointopay_wc_checkout_vars;
  if(data === 'checkoutForm') {
    document.getElementById("checkoutForm").submit();
  } else {
    var setDisabled = function(id, state) {
      if (typeof state === 'undefined') {
        state = true;
      }
      var elem = document.getElementById(id);
      if (state === false) {
        elem.removeAttribute('disabled');
      } else {
        elem.setAttribute('disabled', state);
      }
    };

    // Payment was closed without handler getting called
    data.modal = {
      ondismiss: function() {
        setDisabled('btn-cointopay', false);
      },
    };

    data.handler = function(payment) {
      setDisabled('btn-cointopay-cancel');
      var successMsg = document.getElementById('msg-cointopay-success');
      successMsg.style.display = 'block';
      document.getElementById('cointopay_payment_id').value =
        payment.cointopay_payment_id;
      document.getElementById('cointopay_signature').value =
        payment.cointopay_signature;
      document.cointopayform.submit();
    };

    var cointopayCheckout = new Cointopay(data);

    // global method
    function openCheckout() {
      // Disable the pay button
      setDisabled('btn-cointopay');
      cointopayCheckout.open();
    }

    function addEvent(element, evnt, funct) {
      if (element.attachEvent) {
        return element.attachEvent('on' + evnt, funct);
      } else return element.addEventListener(evnt, funct, false);
    }

    if (document.readyState === 'complete') {
      addEvent(document.getElementById('btn-cointopay'), 'click', openCheckout);
      openCheckout();
    } else {
      document.addEventListener('DOMContentLoaded', function() {
        addEvent(document.getElementById('btn-cointopay'), 'click', openCheckout);
        openCheckout();
      });
    }
  }
})();
