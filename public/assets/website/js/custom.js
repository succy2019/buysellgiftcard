$(document).ready(function(){"use strict";setTimeout(function(){$("body").addClass("loaded")},1e3),$(".page_header").parallaxBackground(),$("#cryptoTable").DataTable({responsive:!0}),$("#forexTable").DataTable({responsive:!0}),$("#stocksTable").DataTable({responsive:!0}),$('a[data-toggle="tab"]').on("shown.bs.tab",function(a){$($.fn.dataTable.tables(!0)).DataTable().columns.adjust()}),$(".table").on("click","tbody tr",function(){window.location.href=$(this).data("href")}),$(".popup-youtube").magnificPopup({disableOn:700,type:"iframe",mainClass:"mfp-fade",removalDelay:160,preloader:!1,fixedContentPos:!1}),$(".accordion > li:eq(0) a").addClass("active").next().slideDown(),$(".accordion a").click(function(a){var e=$(this).closest("li").find("p");$(this).closest(".accordion").find("p").not(e).slideUp(),$(this).hasClass("active")?$(this).removeClass("active"):($(this).closest(".accordion").find("a.active").removeClass("active"),$(this).addClass("active")),e.stop(!1,!0).slideToggle(),a.preventDefault()}),$("#back-to-top").on("click",function(){$("html,body").animate({scrollTop:0},700)}),$("#animation-slide").owlCarousel({items:1,loop:!0,autoplay:!0,dots:!0,nav:!0,autoplayTimeout:5e3,navText:["<i class='lnr lnr-chevron-left'></i>","<i class='lnr lnr-chevron-right'></i>"],autoplayHoverPause:!1,touchDrag:!0,mouseDrag:!0}),$("#animation-slide").on("translate.owl.carousel",function(){$(this).find(".owl-item .slide-text > *").removeClass("fadeInUp animated").css("opacity","0"),$(this).find(".owl-item .slide-img").removeClass("fadeInRight animated").css("opacity","0")}),$("#animation-slide").on("translated.owl.carousel",function(){$(this).find(".owl-item.active .slide-text > *").addClass("fadeInUp animated").css("opacity","1"),$(this).find(".owl-item.active .slide-img").addClass("fadeInRight animated").css("opacity","1")}),$(".owl-blog").owlCarousel({loop:!1,dots:!1,nav:!0,navText:["<i class='fa fa-angle-left'></i>","<i class='fa fa-angle-right'></i>"],responsive:{0:{items:1},600:{items:1},768:{items:2},1000:{items:4}}}),$(".owl-testimonial").owlCarousel({loop:!0,dots:!1,nav:!0,navText:["<i class='fa fa-angle-left'></i>","<i class='fa fa-angle-right'></i>"],responsive:{0:{items:1},600:{items:1},768:{items:2},1000:{items:3}}}),$(".selectpicker").selectpicker({style:""}),$("#marquee-horizontal").marquee({direction:"horizontal",delay:0,timing:15});$(".more").each(function(){var a=$(this).html();if(a.length>550){var e=a.substr(0,550)+'<span class="moreellipses">...&nbsp;</span><span class="morecontent"><span>'+a.substr(550,a.length-550)+'</span>&nbsp;&nbsp;<a href="" class="morelink">Show more ></a></span>';$(this).html(e)}}),$(".morelink").click(function(){return $(this).hasClass("less")?($(this).removeClass("less"),$(this).html("Show more >")):($(this).addClass("less"),$(this).html("Show less")),$(this).parent().prev().toggle(),$(this).prev().toggle(),!1})});
(function ($) {
    var base_url = $("#base_url").val();
    var seg1 = $("#seg1").val();
    var seg2 = $("#seg2").val();
    var language = $("#language").val();
    var crypto_api = $("#crypto_api").val();
    var language = $('#lang-changeF').val();
    var myResponse='';
    $.ajax({
    	url: base_url+'/public/assets/js/language.json',
        async: false,
        type:'POST',
        dataType: 'json',
        global: false,
        contentType: 'application/json',
        success: function (data) {
            var obj    = JSON.stringify(data);
            myResponse = obj;
        }
    });
    //frontend sell transaction end
    var obj = $.parseJSON(myResponse);   
  
	// valid password check
    $('#pass').keyup(function() {
    	var pswd = $(this).val();
    	//validate letter
        console.log( pswd.match(/[a-z]/));
        if ( pswd.match(/[a-z]/) ) {
          $('#letter').removeClass('invalid').addClass('valid');
      } else {
          $('#letter').removeClass('valid').addClass('invalid');
      }
  		//validate capital letter
  		if ( pswd.match(/[A-Z]/) ) {
            $('#capital').removeClass('invalid').addClass('valid');
        } else {
            $('#capital').removeClass('valid').addClass('invalid');
        }
  		//validate number
  		if ( pswd.match(/\d/) ) {
            $('#number').removeClass('invalid').addClass('valid');
        } else {
            $('#number').removeClass('valid').addClass('invalid');
        }
  		//validate special
  		if ( pswd.match(/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/g) ) {
            $('#special').removeClass('invalid').addClass('valid');
        } else {
            $('#special').removeClass('valid').addClass('invalid');
        }
  		//validate the length
  		if ( pswd.length < 8 ) {
            $('#length').removeClass('valid').addClass('invalid');
      } else {
          $('#length').removeClass('invalid').addClass('valid');
      }

  }).focus(function() {
   $('#password_msg').show();

}).blur(function() {
   $('#password_msg').hide();

});


	//Password confirm check
	$('#r_pass,#pass').keyup(function() {
		//Passwod confirm match
		if($('#pass').val() != $('#r_pass').val()) {
           document.getElementById("r_pass").style.borderColor = '#f00';
	        // Prevent form submission
	        event.preventDefault();
	    }else{    	
         document.getElementById("r_pass").style.borderColor = 'unset';
	        // Prevent form submission
	        event.preventDefault();
	    }
    });
    
    $('#reset-pass').click(function() {
      var pass      = $('#pass').val();
      var r_pass    = $('#r_pass').val();
      if (pass == "") {
           alert(obj['password_required'][language]);
           return false;
        }
      else if (!pass.match(/[a-z]/)) {
          alert(obj['a_lowercase_letter'][language]);
          return false;
      } 
      else if (!pass.match(/[A-Z]/)) {
          alert(obj['a_capital_uppercase_letter'][language]);
          return false;
      } 
      else if (!pass.match(/\d/)) {
          alert(obj['a_number'][language]);
          return false;
      } 
      else if (!pass.match(/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/g)) {
          alert(obj['a_special'][language]);
          return false;
      }
      else if (pass.length < 8) {
         alert(obj['please_enter_at_least_8_characters_input'][language]);
         return false;
      }
      else if (r_pass == "") {
         alert(obj['confirm_password_must_be_filled_out'][language]);
         return false;
      }
  });

	//Password confirm check
	$('#email').keyup(function() {
		var email = $(this).val();
       if(IsEmail(email)==false){
          document.getElementById("email").style.borderColor = '#f00';
      }else{
          document.getElementById("email").style.borderColor = 'unset';
      }
  });

	// mail partern check function
	function IsEmail(email) {
		var regex = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
		if(!regex.test(email)) {
			return false;
		}else{
			return true;
		}
	}

	// Registration from validation
	$('#registerForm').on('submit', function(event){
	   var f_name    = $('#f_name').val();
       var l_name    = $('#l_name').val();
       var username  = $('#username').val();
       var email     = $('#email').val();
       var phone     = $('#phone').val();
       var country   = $('#country').val();
       var pass      = $('#pass').val();
       var r_pass    = $('#r_pass').val();
       var checkbox  = $('#accept_terms').val();

      if (f_name == "") {
        alert(obj['first_name_required'][language]);
        return false;
      }
      else if (l_name == "") {
        alert(obj['last_name_required'][language]);
        return false;
      }
        else if (username == "") {
          alert(obj['user_name_required'][language]);
        return false;
      }
      else if (country == "") {
         alert(obj['country_required'][language]);
         return false;
      }
      else if (phone == "") {
         alert(obj['phone_required'][language]);
         return false;
      }
      else if (email == "") {
         alert(obj['email_required'][language]);
         return false;
      }
      else if (pass == "") {
         alert(obj['password_required'][language]);
         return false;
      }
      else if (!pass.match(/[a-z]/)) {
          alert(obj['a_lowercase_letter'][language]);
          return false;
      } 
      else if (!pass.match(/[A-Z]/)) {
          alert(obj['a_capital_uppercase_letter'][language]);
          return false;
      } 
      else if (!pass.match(/\d/)) {
          alert(obj['a_number'][language]);
          return false;
      } 
      else if (!pass.match(/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/g)) {
          alert(obj['a_special'][language]);
          return false;
      }
      else if (pass.length < 8) {
         alert(obj['please_enter_at_least_8_characters_input'][language]);
         return false;
      }
      else if (r_pass == "") {
         alert(obj['confirm_password_must_be_filled_out'][language]);
         return false;
      }
      else if (!($('#checkbox').is(':checked'))) {
          alert(obj['must_confirm_privacy_policy_and_terms_and_conditions'][language]);
          return false;
      }
      else{
       return true;
      }
});


	//Login registration tab
	var url 		= window.location.href;
    var tab 		= url.substring(url.lastIndexOf('#')+1);
    var logintab 	= url.substring(url.lastIndexOf('login'));

    if (tab == 'tab2') {
        $("#btntab2").removeClass("active");
        $("#btntab1").addClass("active");
        $("#tab1").removeClass("in active");
        $("#tab2").addClass("in active");
    }

    if (tab == 'tab1') {
        $("#btntab1").removeClass("active");
        $("#btntab2").addClass("active");
        $("#tab2").removeClass("in active");
        $("#tab1").addClass("in active");
    }
    if (logintab == 'login') {
        $("#btntab2").addClass("active");
        $("#tab2").addClass("in active");
        $("#btntab1").removeClass("active");
        $("#tab1").removeClass("in active");
    }


    //Login registration input field
    $(function () {
        // trim polyfill : https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/Trim
        if (!String.prototype.trim) {
            (function () {
                // Make sure we trim BOM and NBSP
                var rtrim = /^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g;
                String.prototype.trim = function () {
                    return this.replace(rtrim, '');
                };
            })();
        }

        [].slice.call(document.querySelectorAll('input.input__field')).forEach(function (inputEl) {
            // in case the input is already filled..
            if (inputEl.value.trim() !== '') {
                classie.add(inputEl.parentNode, 'input--filled');
            }
            // events:
            inputEl.addEventListener('focus', onInputFocus);
            inputEl.addEventListener('blur', onInputBlur);
        });

        function onInputFocus(ev) {
            classie.add(ev.target.parentNode, 'input--filled');
        }

        function onInputBlur(ev) {
            if (ev.target.value.trim() === '') {
                classie.remove(ev.target.parentNode, 'input--filled');
            }
        }
    });


    $("#country").on("change", function(event) {
        event.preventDefault();
        $("#phone").val(this.value);
        $("#phone").focus();
    });

    if($( ".value_graph").length){
        // Sparkline Ajax

        window.setTimeout(function(){
            $( ".value_graph").text("Loading...");
            $.ajax({
                url: base_url+'/home/coingraphdata',
                type: "GET",
                dataType : "json",
                success: function(result,status,xhr) {
                    var keys = Object.keys(result);
                    for(var i=0;i<keys.length;i++){
                        var key = keys[i];
                        $( "#GRAPH_"+key).text(result[key]);
                        $('#GRAPH_'+key).sparkline('html', {type:'line', height:'40px', lineWidth:1, lineColor:'#35a947', fillColor:false, spotColor:'red'} );
                    }
                },
                error: function(xhr,status,error){
                    console.log(obj['no_data'][language]);
                }
            });
        }, 500);
    }

	// Calculator
    var cryptolistfrom;
    var cryptolistto;
    var amountfrom;
    var amountto;
    $("#convertfromcryptolist").on("change", function(event) {
        event.preventDefault();
        $( "#convertfromcrypto").val(1);
        cryptolistfrom = $("#convertfromcryptolist").val(); 
        cryptolistto = $("#converttocryptolist").val();

        $.getJSON("https://min-api.cryptocompare.com/data/price?fsym="+cryptolistfrom+"&tsyms="+cryptolistto+"&api_key="+crypto_api, function(result) {

        console.log(result);
        console.log("here now");

            if (result[Object.keys(result)[0]]=='Error') {
             alert(obj['no_conversion_found'][language]);
         }
         else {
            $( "#converttocrypto").val(result[Object.keys(result)[0]]);
        };
    });
    });

    $("#converttocryptolist").on("change", function(event) {
        event.preventDefault();
        $( "#converttocrypto").val(1);
        cryptolistfrom = $("#convertfromcryptolist").val(); 
        cryptolistto = $("#converttocryptolist").val();

        $.getJSON("https://min-api.cryptocompare.com/data/price?fsym="+cryptolistto+"&tsyms="+cryptolistfrom+"&api_key="+crypto_api, function(result) {
            if (result[Object.keys(result)[0]]=='Error') {
             alert(obj['no_conversion_found'][language]);
         }
         else {
            $( "#convertfromcrypto").val(result[Object.keys(result)[0]]);
        };
    });
    });

    $("#convertfromcrypto").on("keyup click", function(event) {
        event.preventDefault();
        cryptolistfrom = $("#convertfromcryptolist").val();
        cryptolistto = $("#converttocryptolist").val();
        amountfrom = parseFloat($("#convertfromcrypto").val()) || 0;

        $.getJSON("https://min-api.cryptocompare.com/data/price?fsym="+cryptolistfrom+"&tsyms="+cryptolistto+"&api_key="+crypto_api, function(result) {
            if (result[Object.keys(result)[0]]=='Error') {
             alert(obj['no_conversion_found'][language]);
         }
         else {
             //console.log(amountfrom);
            $( "#converttocrypto").val(result[Object.keys(result)[0]]*amountfrom);
        };
    });

    });

    $("#converttocrypto").on("keyup", function(event) {
        event.preventDefault();
        cryptolistfrom = $("#convertfromcryptolist").val();
        cryptolistto = $("#converttocryptolist").val();
        amountto = parseFloat($("#converttocrypto").val())|| 1;

        $.getJSON("https://min-api.cryptocompare.com/data/price?fsym="+cryptolistto+"&tsyms="+cryptolistfrom+"&api_key="+crypto_api, function(result) {
            if (result[Object.keys(result)[0]]=='Error') {
             alert(obj['no_conversion_found'][language]);
         }
         else {
            $("#convertfromcrypto").val(result[Object.keys(result)[0]]*amountto);
        };
    });
    });

    if($('.selectpicker').length){
        $('.selectpicker').selectpicker();
    }

	// Ajax Language Change
    $("#langForm").on("change", function(event) {
        event.preventDefault();

        var inputdata 	= $("#langForm").serialize();
        $.ajax({
            url: base_url+'/home/langChange',
            type: "post",
            data: inputdata,
            success: function(result,status,xhr) {
                location.reload();
            },
            error: function(xhr,status,error){
                location.reload();
            }
        });
    });

    // Ajax Subscription
    $("#subscribeForm").on("submit", function(event) {
        event.preventDefault();
        var inputdata 	= $("#subscribeForm").serialize();
        var email 		= $('input[name=subscribe_email]').val();

        if (email == "") {
            alert(obj['please_enter_valid_email'][language]);
            return false;
        }
        if (IsEmail(email)==false) {
            alert(obj['please_enter_valid_email'][language]);
            return false;
        }

        $.ajax({
            url: base_url+'/subscribe',
            type: "post",
            data: inputdata,
            dataType : 'json',
            success: function(data) {
                if(data.status == 'exists'){
                    alert(obj['this_email_address_already_subscribed'][language]);
                    location.reload();
                }else{
                    alert(obj['subscribtion_complete'][language]);
                    location.reload();
                }
            }
        });
    });

    if($(".list-item-currency span").length){
        window.setTimeout(function(){
            $(".list-item-currency span").text("Loading...");
            $.ajax({
                url: base_url+'/home/cointrickerdata',
                type: "GET",
                dataType : "json",
                success: function(result,status,xhr) {

                    var keys = Object.keys(result);
                    for(var i=0;i<keys.length;i++){
                        var key = keys[i];
                        $( "#"+key+" .list-item-currency").text(key+"USD");
                        $( "#"+key+" .upgrade").html("<span>"+result[key]+"</span>");
                    }

                },
                error: function(xhr,status,error){

                }
            });
        }, 100);
    }

	// Ajax Contract From
    $("#contactForm").on("submit", function(event) {
        event.preventDefault();

        var f_name  = $('#f_name').val();
        var phone   = $('#phone').val();
        var email   = $('#email').val();
        var comment = $('#comment').val();

        if(phone == ""){
          alert(obj['phone_required'][language]);
           return false;
        } else if (email == "") {
           alert(obj['email_required'][language]);
           return false;
        } else if (comment == "") {
           alert(obj['comments_required'][language]);
           return false;
        }
        
        var inputdata = $("#contactForm").serialize();
        $.ajax({
            url: base_url+'/home/contactMsg',
            type: "post",
            data: inputdata,
            success: function(d) {
                alert(obj['message_send_successfuly'][language]);
                location.reload();
            },
            error: function(){
                alert(obj['message_send_fail'][language]);
            }
        });
    });

    if(seg1=='buy'){
        // Ajax Buy Crypto 
        $("#cid").on("change", function(event) {
            event.preventDefault();
            var cid = $("#cid").val()|| 0;

            var inputdata = $("#buyForm").serialize();
            $.ajax({
                url: base_url+'/home/buypayable',
                type: "post",
                data: inputdata,
                success: function(data) {
                    $( ".buy_payable").html(data);
                    $( "#buy_amount" ).prop( "disabled", false );
                },
                error: function(){

                }
            });
        });

        $("#buy_amount").on("keyup", function(event) {
            event.preventDefault();

            var buy_amount = parseFloat($("#buy_amount").val())|| 0;
            var cid = $("#cid").val()|| 0;
            if (cid=="") {
                alert(obj['please_select_cryptocurrency_first'][language]);
                return false;
            } else {
            	var inputdata = $("#buyForm").serialize();

               $.ajax({
                url: base_url+'/home/buypayable',
                type: "post",
                data: inputdata,
                success: function(data) {
                    $( ".buy_payable").html(data);
                },
                error: function(){
                    return false;
                }
            });
           }
       });

        $("#payment_method").on("change", function(event) {
            event.preventDefault();
            var payment_method = $("#payment_method").val()|| 0;
            var cid = $("#cid").val()|| 0;

            if (payment_method==='bitcoin' && cid==1) {
                alert(obj['please_select_diffrent_payment_method'][language]);
                $('#payment_method option:selected').removeAttr('selected');
                return false;
            }
            
            if (payment_method==='phone') {
            	$.getJSON(base_url+'/internal_api/gateway', function(result) {

                   $(".payment_info").html("<div class='form-group row'><label for='send_money' class='col-sm-4 control-label'>"+obj['send_money'][language]+"</label><div class='col-sm-8'><h2><a href='tel:"+result.public_key+"'>"+result.public_key+"</a></h2></div></div><div class='form-group row'><label class='col-sm-4 control-label'>"+obj['om_name'][language]+"</label><div class='col-sm-8'><input name='om_name' class='form-control input-solid om_name' type='text' id='om_name' autocomplete='off'></div></div><div class='form-group row'><label class='col-sm-4 control-label'>"+obj['om_mobile_no'][language]+"</label><div class='col-sm-8'><input name='om_mobile' class='form-control input-solid om_mobile' type='text' id='om_mobile' autocomplete='off'></div></div><div class='form-group row'><label class='col-sm-4 control-label'>"+obj['transaction_no'][language]+"</label><div class='col-sm-8'><input name='transaction_no' class='form-control input-solid transaction_no' type='text' id='transaction_no' autocomplete='off'></div></div><div class='form-group row'><label class='col-sm-4 control-label'>"+obj['idcard_no'][language]+"</label><div class='col-sm-8'><input name='idcard_no' class='form-control input-solid idcard_no' type='text' id='idcard_no' autocomplete='off'></div></div>");

               });
            }
            else{
                $(".payment_info").html("<div class='form-group row'><label class='col-sm-4 control-label'>"+obj['comments'][language]+"</label><div class='col-sm-8'><textarea name='comments' class='form-control input-solid' placeholder='' type='text' id='comments' autocomplete='off'></textarea></div></div>");
            }
        });
    };
    if(seg1=='sells'){
	// Ajax Sel
    $("#cid").on("change", function(event) {
        event.preventDefault();
        var cid = $("#cid").val()|| 0;

        var inputdata = $("#sellform").serialize();

        $.ajax({
            url: base_url+'/home/sellpayable',
            type: "post",
            data: inputdata,
            success: function(data) {
                $(".sell_payable").html(data);
                $("#sell_amount" ).prop( "disabled", false );
            },
            error: function(x){
                return false;
            }
        });
    });

    $("#sell_amount").on("keyup", function(event) {
        event.preventDefault();
        var sell_amount = parseFloat($("#sell_amount").val())|| 0;
        var cid = $("#cid").val()|| 0;
        if (cid=="") {
            alert(obj['please_select_cryptocurrency_first'][language]);
            return false;
        } else {
           var inputdata = $("#sellform").serialize();

           $.ajax({
            url: base_url+'/home/sellpayable',
            type: "post",
            data: inputdata,
            success: function(data) {
                $( ".sell_payable").html(data);
            },
            error: function(){
                return false;
            }
        });
       }
   });

    $("#payment_method").on("change", function(event) {
        event.preventDefault();
        var payment_method = $("#payment_method").val()|| 0;

        if (payment_method==='bitcoin') {
            $(".payment_info").html("<div class='form-group row'><label class='col-sm-4 control-label comments_level'>"+obj['bitcoin_wallet_id'][language]+"</label><div class='col-sm-8'><textarea name='comments' class='form-control input-solid input-solid input-solid' placeholder='' type='text' id='comments' autocomplete='off'></textarea></div></div>");
        }else if(payment_method==='payeer'){
         $(".payment_info").html("<div class='form-group row'><label class='col-sm-4 control-label comments_level'>"+obj['payeer_wallet_id'][language]+"</label><div class='col-sm-8'><textarea name='comments' class='form-control input-solid input-solid input-solid' placeholder='' type='text' id='comments' autocomplete='off'></textarea></div></div>");
     }else if(payment_method==='phone'){
       $.getJSON(base_url+'/internal_api/gateway', function(result) {
           $(".payment_info").html("<div class='form-group row'><label for='send_money' class='col-sm-4 control-label'>"+obj['send_money'][language]+"</label><div class='col-sm-8'><h2><a href='tel:"+result.public_key+"'>"+result.public_key+"</a></h2></div></div><div class='form-group row'><label class='col-sm-4 control-label'>"+obj['om_name'][language]+"</label><div class='col-sm-8'><input name='om_name' class='form-control input-solid input-solid om_name' type='text' id='om_name' autocomplete='off'></div></div><div class='form-group row'><label class='col-sm-4 control-label'>"+obj['om_mobile_no'][language]+"</label><div class='col-sm-8'><input name='om_mobile' class='form-control input-solid input-solid om_mobile' type='text' id='om_mobile' autocomplete='off'></div></div><div class='form-group row'><label class='col-sm-4 control-label'>"+obj['transaction_no'][language]+"</label><div class='col-sm-8'><input name='transaction_no' class='form-control input-solid input-solid transaction_no' type='text' id='transaction_no' autocomplete='off'></div></div><div class='form-group row'><label class='col-sm-4 control-label'>"+obj['idcard_no'][language]+"</label><div class='col-sm-8'><input name='idcard_no' class='form-control input-solid input-solid idcard_no' type='text' id='idcard_no' autocomplete='off'></div></div>");
       });
   }
   else{
    $(".payment_info").html("<div class='form-group row'><label class='col-sm-4 control-label'>"+obj['comments'][language]+"</label><div class='col-sm-8'><textarea name='comments' class='form-control input-solid' placeholder='' type='text' id='comments' autocomplete='off'></textarea></div></div>");
}
});
//frotend sell js start

};
console.log($("#loginForm").length);
// if(!$("#loginForm").length){
$("#tab2 form").submit(function(e){
        toastr.warning('It is disabled for demo mode');
        return false;
        
});

$(".hide_for_demo form").submit(function(e){
        toastr.warning('It is disabled for demo mode');
        return false;
        
});
// }

    
}(jQuery));




