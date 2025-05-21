jQuery(document).ready(function($){
    $('#spinBtn').on('click', function(){
        const prizes = ['5% OFF', '10% OFF', 'Try Again', 'Free Shipping'];
        const result = prizes[Math.floor(Math.random() * prizes.length)];
        $('#spin-result').html("<strong>You won:</strong> " + result);
    });
});
