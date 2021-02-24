
$( document ).ready(function( $ ) {

    $.urlParam = function (name) {
        var results = new RegExp('[\?&]' + name + '=([^&#]*)')
            .exec(window.location.search);
        return (results !== null) ? results[1] || 0 : false;
    }

    $(document).on('opened', '.remodal', function () {
        var id = $.urlParam('process');
        var url = document.getElementById(id).getAttribute("data-action");
        $('#modal-container').load(url);
    });

    $('[data-remodal-id=modal]').remodal({
        modifier: 'with-red-theme',
        closeOnOutsideClick: true
    });


    $(document).on('change', '.action', function () {

        $.ajax({
            url:  $('form#postForm').attr('data-action'),
            type: $('form#postForm').attr('method'),
            data: new FormData($('form#postForm')[0]),
            processData: false,
            contentType: false,
            success: function (response) {
                location.reload();
            }

    });

    });

    $(document).on('change', '.matrixId', function() {

        var quantity = $(this).val();
        var id = $(this).attr('data-id');
        var url = $(this).attr('data-action');
        if(quantity === ''){
            $('#amount-'+id).focus();
            return false;
        }
        $.get(url, { quantity:quantity } );
    });

    $('#inputform').on('keydown', 'input', function (event) {

        if (event.which === 13) {
            event.preventDefault();
            var $this = $(event.target);
            var index = parseFloat($this.attr('data-index'));
            $('[data-index="' + (index + 1).toString() + '"]').focus();
        }
    });

    var explode = function AutoReload()
    {
        $('#entityDatatable').each(function() {
            dt = $(this).dataTable();
            dt.fnDraw();
        });
    }

    $("#allCheck").change(function(){  //"select all" change
        $(".process").prop('checked', $(this).prop("checked")); //change all ".checkbox" checked status
        $('#check-btn').toggle();
    });
    $(document).on('click', '#checkBtn', function(e) {
        var favorite = [];
        $.each($("input[name='process']:checked"), function(){
            favorite.push($(this).val());
        });
        ids = favorite.join(", ");
        var url = $('#checkBtn').attr('data-action');
        $.get( url , { ids:ids } )
            .done(function( data ) {
                dt = $(this).dataTable();
                dt.fnDraw();
            });

    });


});




