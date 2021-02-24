$( ".date-picker" ).datepicker({
    dateFormat: "dd-mm-yy"
});

$( ".dateCalendar" ).datepicker({
    dateFormat: "dd-mm-yy",
    changeMonth: true,
    changeYear: true,
    yearRange: "-100:+0",
});

var bindDatePicker = function(element) {
    $(element).datetimepicker({
        showOn: "button",
        buttonImage: "/img/calendar_icon.png",
        buttonImageOnly: true,
        dateFormat: 'mm-dd-yy',
        timeFormat: 'hh:mm tt',
        stepMinute: 1,
        onClose: datePickerClose
    });
};

function datePickerReload() {
    $( ".date-picker" ).datepicker({
        dateFormat: "dd-mm-yy"
    });
}

$("[id^=startPicker]").each(function() {
    bindDatePicker(this);
});

$('#startDate, #endDate').datepicker({
    showOn: "both",
    beforeShow: customRange,
    dateFormat: "dd-mm-yy",
});
function customRange(input) {
    if (input.id == 'endDate') {
        var minDate = new Date($('#startDate').val());
        minDate.setDate(minDate.getDate())
        return {
            minDate: minDate
        };
    }
    return {}
}

$(document).on("click", ".sms-confirm", function() {
    var url = $(this).attr('data-url');
    $('#confirm-content').confirmModal({
        topOffset: 0,
        top: '25%',
        onOkBut: function(event, el) {
            $.get(url);
        }
    });
});

/*$( "#mobile" ).autocomplete({

    source: function( request, response ) {
        $.ajax( {
            url: Routing.generate('domain_customer_auto_mobile_search'),
            data: {
                term: request.term
            },
            success: function( data ) {
                response( data );
            }
        } );
    },
    minLength: 2,
    select: function( event, ui ) {}

});*/

$(document).on('change', '.transactionMethod', function() {

    var paymentMethod = $(this).val();

    if( paymentMethod == 2){
        $('#cartMethod').css({ 'display': "block" });
        $('#bkashMethod').hide();
    }else if( paymentMethod == 3){
        $('#bkashMethod').css({ 'display': "block" });
        $('#cartMethod').hide();
    }else{
        $('#cartMethod').hide();
        $('#bkashMethod').hide();
    }

});

$(".addCustomer").click(function(){
    $( ".customer" ).slideToggle( "slow" );
}).toggle( function() {
    $(this).removeClass("blue").addClass("red").html('<i class="icon-remove"></i>');
}, function() {
    $(this).removeClass("red").addClass("blue").html('<i class="icon-user"></i>');
});

var form = $("#customInvoice").validate({

    rules: {

        "customParticular": {required: true},
        "price": {required: true},
        "quantity": {required: false},
        "unit": {required: false},
    },

    messages: {

        "customParticular":"Enter particular name",
        "price":"Enter sales price",
    },
    tooltip_options: {
        "customParticular": {placement:'top',html:true},
        "price": {placement:'top',html:true},
    },

    submitHandler: function(form) {

        $.ajax({
            url         : $('form#customInvoice').attr( 'action' ),
            type        : $('form#customInvoice').attr( 'method' ),
            data        : new FormData($('form#customInvoice')[0]),
            processData : false,
            contentType : false,
            success: function(response){
                obj = JSON.parse(response);
                $('#invoiceParticulars').html(obj['invoiceParticulars']);
                $('.subTotal').html(obj['subTotal']);
                $('.netTotal').html(obj['netTotal']);
                $('#paymentTotal').val(obj['netTotal']);
                $('#due').val(obj['due']);
                $('.due').html(obj['due']);
                $('.payment').html(obj['payment']);
                $('.discount').html(obj['discount']);
                $('#customInvoice')[0].reset();
            }
        });
    }
});

$(document).on('change', '.quantity , .salesPrice', function() {

    var id = $(this).attr('data-id');

    var quantity = parseFloat($('#quantity-'+id).val());
    var price = parseFloat($('#salesPrice-'+id).val());
    var subQuantity = parseFloat($('#subQuantity-'+id).val());
    var subTotal  = (quantity * subQuantity * price);
    $("#subTotal-"+id).html(subTotal);
    $.ajax({
        url: Routing.generate('business_invoice_item_update'),
        type: 'POST',
        data:'itemId='+ id +'&quantity='+ quantity +'&salesPrice='+ price,
        success: function(response) {
            obj = JSON.parse(response);
            $('.subTotal').html(obj['subTotal']);
            $('.netTotal').html(obj['netTotal']);
            $('#paymentTotal').val(obj['netTotal']);
            $('#due').val(obj['due']);
            $('.due').html(obj['due']);
            $('.payment').html(obj['payment']);
            $('.discount').html(obj['discount']);

        },

    })
});

$(document).on('click', '.itemUpdate', function() {

    var id = $(this).attr('data-id');
    var quantity = parseFloat($('#quantity-'+id).val());
    var price = parseFloat($('#salesPrice-'+id).val());
    var subTotal  = (quantity * price);
    $("#subTotal-"+id).html(subTotal);
    $.ajax({
        url: Routing.generate('business_invoice_item_update'),
        type: 'POST',
        data:'itemId='+ id +'&quantity='+ quantity +'&salesPrice='+ price,
        success: function(response) {
            obj = JSON.parse(response);
            $('.subTotal').html(obj['subTotal']);
            $('.netTotal').html(obj['netTotal']);
            $('#paymentTotal').val(obj['netTotal']);
            $('#due').val(obj['due']);
            $('.due').html(obj['due']);
            $('.payment').html(obj['payment']);
            $('.discount').html(obj['discount']);

        },

    })
});


$(document).on("click", ".particularDelete", function() {

    var id = $(this).attr("data-id");
    var url = $(this).attr("data-url");
       $('#confirm-content').confirmModal({
        topOffset: 0,
        top: '25%',
        onOkBut: function(event, el) {
            $.get(url, function( response ) {
                obj = JSON.parse(response);
                $('#remove-'+id).remove();
                $('.subTotal').html(obj['subTotal']);
                $('.netTotal').html(obj['netTotal']);
                $('#paymentTotal').val(obj['netTotal']);
                $('#due').val(obj['due']);
                $('.due').html(obj['due']);
                $('.payment').html(obj['payment']);
                $('.discount').html(obj['discount']);


            });
        }
    });
});


$(document).on("click", ".approve", function() {

    var id = $(this).attr("data-id");
    var url = $(this).attr("data-url");
    $('#treatment-approved-'+id).hide();
    $('#confirm-content').confirmModal({
        topOffset: 0,
        top: '25%',
        onOkBut: function(event, el) {
            $.get(url, function( data ) {
                if(data == 'success'){
                    location.reload();
                }
            });
        }
    });
});

$(document).on('change', '#particular', function() {

    var particular = $('#particular').val();
    $.ajax({
        url: Routing.generate('business_particular_search',{'id':particular}),
        type: 'GET',
        success: function (response) {
            obj = JSON.parse(response);
            $('#unit').html(obj['unit']);
            $('#salesPrice').val(obj['salesPrice']);
            $('#subTotal').html(obj['salesPrice']);
        }
    })

});

$(document).on('keyup', '#width , #height ', function() {

    var width = parseFloat($('#width').val());
    var height = parseFloat($('#height').val());
    var salesPrice = parseFloat($('#salesPrice').val());
    var quantity = parseInt($('#quantity').val());

    if((!isNaN(width) && jQuery.isNumeric(width)) && !isNaN(height) && jQuery.isNumeric(height)) {

        $('#subQuantity').html(width * height);
        var total = (width * height) * quantity;
        $('#subTotal').html(total * salesPrice);

    }

});

$(document).on('keyup', '#quantity , #salesPrice ', function() {

    var width = parseFloat($('#width').val());
    var height = parseFloat($('#height').val());
    var salesPrice = parseFloat($('#salesPrice').val());
    var quantity = parseInt($('#quantity').val());

    if(isNaN(width) && !jQuery.isNumeric(width)) {
        $('#subTotal').html(quantity * salesPrice);
    }else{
        $('#subQuantity').html(width * height);
        var total = (width * height) * quantity;
        $('#subTotal').html(total * salesPrice);
    }

});

var stockInvoice = $("#stockInvoice").validate({

    rules: {

        "particular": {required: true},
        "quantity": {required: true},
        "salesPrice": {required: true}
    },

    messages: {

        "particular":"Enter particular name",
        "salesPrice":"Enter sales price",
        "quantity":"Enter sales quantity"
    },

    tooltip_options: {
        "particular": {placement:'top',html:true},
        "salesPrice": {placement:'top',html:true},
        "quantity": {placement:'top',html:true}
    },

    submitHandler: function(stockInvoice) {

        $.ajax({
            url         : $('form#stockInvoice').attr( 'action' ),
            type        : $('form#stockInvoice').attr( 'method' ),
            data        : new FormData($('form#stockInvoice')[0]),
            processData : false,
            contentType : false,
            success: function(response){
                obj = JSON.parse(response);
                $('#invoiceParticulars').html(obj['invoiceParticulars']);
                $('.subTotal').html(obj['subTotal']);
                $('.netTotal').html(obj['netTotal']);
                $('#paymentTotal').val(obj['netTotal']);
                $('#due').val(obj['due']);
                $('.due').html(obj['due']);
                $('.payment').html(obj['payment']);
                $('.discount').html(obj['discount']);
                $('#subQuantity').html('0');
                $('#unit').html('Unit');
                $("#particular").select2().select2("val","");
                $('#stockInvoice')[0].reset();
            }
        });
    }
});


$(document).on('keyup', '#businessInvoice_discountCalculation , #businessInvoice_discountType', function() {

    var discountType = $('#businessInvoice_discountType').val();
    var discount = parseInt($('#businessInvoice_discountCalculation').val() !="" ? $('#businessInvoice_discountCalculation').val() : 0);
    var invoice = $('#invoiceId').val();
    var total =  parseInt($('#paymentTotal').val());
    if( discount >= total ){
        $('#sales_discount').val(0);
        return false;
    }
    $.ajax({
        url: Routing.generate('business_invoice_discount_update'),
        type: 'POST',
        data:'discount=' + discount+'&discountType='+discountType+'&invoice='+invoice,
        success: function(response) {
            obj = JSON.parse(response);
            $('#subTotal').html(obj['subTotal']);
            $('.netTotal').html(obj['netTotal']);
            $('#paymentTotal').val(obj['netTotal']);
            $('#sales_discount').val(obj['discount']);
            $('.discount').html(obj['discount']);
            $('#due').val(obj['due']);
            $('.due').html(obj['due']);
        }

    })

});

$(document).on('keyup', '#businessInvoice_received', function() {

    var payment     = parseInt($('#businessInvoice_received').val()  != '' ? $('#businessInvoice_received').val() : 0 );
    var paymentTotal =  parseInt($('#paymentTotal').val());
    var dueAmount = (paymentTotal-payment);
    if(dueAmount > 0){
        $('#balance').html('Due Tk.');
        $('.due').html(dueAmount);
    }else{
        var balance =  payment - paymentTotal ;
        $('#balance').html('Return Tk.');
        $('.due').html(balance);
    }
});

$('form#salesForm').on('keypress', '.salesInput', function (e) {

    if (e.which === 13) {
        var inputs = $(this).parents("form").eq(0).find("input,select");
        var idx = inputs.index(this);
        if (idx == inputs.length-1) {
            inputs[0].select()
        } else {
            inputs[idx + 1].focus(); //  handles submit buttons
        }
        switch (this.id) {
            case 'sales_transactionMethod':
                $('#sales_salesBy').focus();
                break;
            case 'sales_salesBy':
                $('#sales_received').focus();
                break;
            case 'sales_received':
                $('#receiveBtn').focus();
                break;
        }
        return false;
    }
});

$(document).on("click", "#receiveBtn", function() {

    $('#confirm-content').confirmModal({
        topOffset: 0,
        top: '25%',
        onOkBut: function(event, el) {
            $('form#invoiceForm').submit();
        }
    });
});

$(document).on("change", ".eventProcess", function(e) {
    var formData = new FormData($('form#eventForm')[0]); // Create an arbitrary FormData instance
    var url = $('form#eventForm').attr('action'); // Create an arbitrary FormData instance
    $.ajax(url,{
        processData: false,
        contentType: false,
        type: 'POST',
        data: formData,
        success: function (response){}
    });

});

$(document).on('change', '#vendor', function() {

    var vendor = $(this).val();
    var particular = $('#particular').val();
    $.ajax({
        url: Routing.generate('business_vendor_stock_search',{'vendor':vendor,'particular':particular}),
        type: 'GET',
        success: function (response) {
            $('#stockGrn').html(response);
        }
    })

});


$(document).on('change', '.salesQuantity , .bonusQuantity , .returnQuantity , .damageQuantity', function() {

    var id = $(this).attr('data-id');

    var price = parseFloat($('#salesPrice-'+id).val());
    var salesQuantity = parseFloat($('#salesQuantity-'+id).val());
    var bonusQuantity = parseFloat($('#bonusQuantity-'+id).val());
    var returnQuantity = parseFloat($('#returnQuantity-'+id).val());
    var damageQuantity = parseFloat($('#damageQuantity-'+id).val());
    totalQuantity  = (salesQuantity - returnQuantity - damageQuantity);
    subTotal  = (totalQuantity * price);
    $("#totalQuantity-"+id).html(totalQuantity);
    $("#subTotal-"+id).html(subTotal);
    $.ajax({
        url: Routing.generate('business_invoice_distribution_item_update'),
        type: 'POST',
        data:'itemId='+ id +'&salesPrice='+ price +'&salesQuantity='+ salesQuantity +'&bonusQuantity='+ bonusQuantity +'&returnQuantity='+ returnQuantity +'&damageQuantity='+ damageQuantity +'&totalQuantity='+ totalQuantity,
        success: function(response) {
            obj = JSON.parse(response);
            $('.subTotal').html(obj['subTotal']);
            $('.netTotal').html(obj['subTotal']);
            $('#paymentTotal').val(obj['subTotal']);
            $('#due').val(obj['subTotal']);
            $('.due').html(obj['subTotal']);
            $('.payment').html(obj['subTotal']);
            $('.discount').html(0);
            $('.salesQnt').html(obj['salesQnt']);
            $('.returnQnt').html(obj['returnQnt']);
            $('.damageQnt').html(obj['damageQnt']);
            $('.totalQnt').html(obj['totalQnt']);
            $('.bonusQnt').html(obj['bonusQnt']);

        },
    })
});

