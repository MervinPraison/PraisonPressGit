jQuery(document).ready(function($) {
    var rollbackHash = '';
    var rollbackNonce = '';
    
    $('.praisonpress-rollback-btn').on('click', function() {
        var $btn = $(this);
        rollbackHash = $btn.data('hash');
        rollbackNonce = $btn.data('nonce');
        var message = $btn.data('message');
        
        $('#praisonpress-rollback-message').text('Rollback to: ' + message);
        $('#praisonpress-rollback-modal').css('display', 'flex').hide().fadeIn(200);
    });
    
    $('.praisonpress-modal-close').on('click', function() {
        $('#praisonpress-rollback-modal').fadeOut(200);
    });
    
    $('#praisonpress-rollback-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).fadeOut(200);
        }
    });
    
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#praisonpress-rollback-modal').is(':visible')) {
            $('#praisonpress-rollback-modal').fadeOut(200);
        }
    });
    
    $('#praisonpress-confirm-rollback').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Rolling back...');
        
        var url = praisonHistoryPage.adminPostUrl + '?action=praison_rollback&hash=' + rollbackHash + '&_wpnonce=' + rollbackNonce;
        window.location.href = url;
    });
});
