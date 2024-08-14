(function ($) {
"user strict";

// preloader
$(window).on('load', function() {
  $(".preloader").delay(800).animate({
    "opacity": "0"
  }, 800, function () {
      $(".preloader").css("display", "none");
  });
});

// header-fixed
var fixed_top = $(".header-section");
$(window).on("scroll", function(){
    if( $(window).scrollTop() > 100){
        fixed_top.addClass("animated fadeInDown header-fixed");
    }
    else{
        fixed_top.removeClass("animated fadeInDown header-fixed");
    }
});

//Create Background Image
(function background() {
  let img = $('.bg_img');
  img.css('background-image', function () {
    var bg = ('url(' + $(this).data('background') + ')');
    return bg;
  });
})();

// scroll-to-top
var ScrollTop = $(".scrollToTop");
$(window).on('scroll', function () {
  if ($(this).scrollTop() < 100) {
      ScrollTop.removeClass("active");
  } else {
      ScrollTop.addClass("active");
  }
});

//Odometer
if ($(".statistics-item,.icon-box-items,.counter-single-items").length) {
  $(".statistics-item,.icon-box-items,.counter-single-items").each(function () {
    $(this).isInViewport(function (status) {
      if (status === "entered") {
        for (var i = 0; i < document.querySelectorAll(".odometer").length; i++) {
          var el = document.querySelectorAll('.odometer')[i];
          el.innerHTML = el.getAttribute("data-odometer-final");
        }
      }
    });
  });
}

// faq
$('.faq-wrapper .faq-title').on('click', function (e) {
  var element = $(this).parent('.faq-item');
  if (element.hasClass('open')) {
    element.removeClass('open');
    element.find('.faq-content').removeClass('open');
    element.find('.faq-content').slideUp(300, "swing");
  } else {
    element.addClass('open');
    element.children('.faq-content').slideDown(300, "swing");
    element.siblings('.faq-item').children('.faq-content').slideUp(300, "swing");
    element.siblings('.faq-item').removeClass('open');
    element.siblings('.faq-item').find('.faq-title').removeClass('open');
    element.siblings('.taq-item').find('.faq-content').slideUp(300, "swing");
  }
});

// slider
var swiper = new Swiper(".testimonial-slider", {
  loop: true,
  effect: "fade",
  fadeEffect: { crossFade: true },
  pagination: {
    el: ".swiper-pagination",
    clickable: true,
  },
  autoplay: {
    speed: 1000,
    delay: 4000,
  },
});

// nice-select
$(".nice-select").niceSelect(),

// select2
$('.select2-basic').select2();
$('.select2-multi-select').select2();
$(".select2-auto-tokenize").select2({
tags: true,
tokenSeparators: [',']
})

// custom Select
$('.custom-select').on('click', function (e) {
  e.preventDefault();
  $(".custom-select-wrapper").removeClass("active");
  if($(this).siblings(".custom-select-wrapper").hasClass('active')) {
    $(this).siblings(".custom-select-wrapper").removeClass('active');
  }else {
    $(this).siblings(".custom-select-wrapper").addClass('active');
  }
});

$('.custom-option').on('click', function(){
  $(this).parent().find(".custom-option").removeClass("active");
  $(this).addClass('active');
  var flag = $(this).find("img").attr("src");
  var currencyCode = $(this).find(".custom-currency").text();
  $(this).parents(".custom-select-wrapper").siblings(".custom-select").find(".custom-select-inner").find(".custom-currency").text(currencyCode);
  $(this).parents(".custom-select-wrapper").siblings(".custom-select").find(".custom-select-inner").find("img").attr("src",flag);
  $(this).parents(".custom-select-wrapper").removeClass("active");
});

$('.custom-option').on('click', function(){
  $(this).parent().find(".custom-option").removeClass("active");
  $(this).addClass('active');
  var method = $(this).find(".title").text();
  $(this).parents(".custom-select-wrapper").siblings(".custom-select").find(".custom-select-inner").find(".method").text(method);
  $(this).parents(".custom-select-wrapper").removeClass("active");
});

// sidebar
$(".sidebar-menu-item > a").on("click", function () {
  var element = $(this).parent("li");
  if (element.hasClass("active")) {
    element.removeClass("active");
    element.children("ul").slideUp(500);
  }
  else {
    element.siblings("li").removeClass('active');
    element.addClass("active");
    element.siblings("li").find("ul").slideUp(500);
    element.children('ul').slideDown(500);
  }
});

// active menu JS
function splitSlash(data) {
  return data.split('/').pop();
}
function splitQuestion(data) {
  return data.split('?').shift().trim();
}
var pageNavLis = $('.sidebar-menu a');
var dividePath = splitSlash(window.location.href);
var divideGetData = splitQuestion(dividePath);
var currentPageUrl = divideGetData;

// find current sidevar element
$.each(pageNavLis,function(index,item){
    var anchoreTag = $(item);
    var anchoreTagHref = $(item).attr('href');
    var index = anchoreTagHref.indexOf('/');
    var getUri = "";
    if(index != -1) {
      // split with /
      getUri = splitSlash(anchoreTagHref);
      getUri = splitQuestion(getUri);
    }else {
      getUri = splitQuestion(anchoreTagHref);
    }
    if(getUri == currentPageUrl) {
      var thisElementParent = anchoreTag.parents('.sidebar-menu-item');
      (anchoreTag.hasClass('nav-link') == true) ? anchoreTag.addClass('active') : thisElementParent.addClass('active');
      (anchoreTag.parents('.sidebar-dropdown')) ? anchoreTag.parents('.sidebar-dropdown').addClass('active') : '';
      (thisElementParent.find('.sidebar-submenu')) ? thisElementParent.find('.sidebar-submenu').slideDown("slow") : '';
      return false;
    }
});

//sidebar Menu
$('.sidebar-menu-bar').on('click', function (e) {
  e.preventDefault();
  if($('.sidebar, .navbar-wrapper, .body-wrapper').hasClass('active')) {
    $('.sidebar, .navbar-wrapper, .body-wrapper').removeClass('active');
    $('.body-overlay').removeClass('active');
  }else {
    $('.sidebar, .navbar-wrapper, .body-wrapper').addClass('active');
    $('.body-overlay').addClass('active');
  }
});
$('#body-overlay').on('click', function (e) {
  e.preventDefault();
  $('.sidebar, .navbar-wrapper, .body-wrapper').removeClass('active');
  $('.body-overlay').removeClass('active');
});

// dashboard-list
$(document).on('click', '.dashboard-list-item', function (e) {
  var element = $(this).parent('.dashboard-list-item-wrapper');
  if (element.hasClass('show')) {
    element.removeClass('show');
    element.find('.preview-list-wrapper').removeClass('show');
    element.find('.preview-list-wrapper').slideUp(300, "swing");
  } else {
    element.addClass('show');
    element.children('.preview-list-wrapper').slideDown(300, "swing");
    element.siblings('.dashboard-list-item-wrapper').children('.preview-list-wrapper').slideUp(300, "swing");
    element.siblings('.dashboard-list-item-wrapper').removeClass('show');
    element.siblings('.dashboard-list-item-wrapper').find('.dashboard-list-item').removeClass('show');
    element.siblings('.dashboard-list-item-wrapper').find('.preview-list-wrapper').slideUp(300, "swing");
  }
});

//info-btn
$(document).on('click', '.info-btn', function () {
  $('.support-profile-wrapper').addClass('active');
});
$(document).on('click', '.chat-cross-btn', function () {
  $('.support-profile-wrapper').removeClass('active');
});

//Notification
$('.notification-icon').on('click', function (e) {
  e.preventDefault();
  if($('.notification-wrapper').hasClass('active')) {
    $('.notification-wrapper').removeClass('active');
    $('.body-overlay').removeClass('active');
  }else {
    $('.notification-wrapper').addClass('active');
    $('.body-overlay').addClass('active');
  }
});
$('#body-overlay').on('click', function (e) {
  e.preventDefault();
  $('.notification-wrapper').removeClass('active');
  $('.body-overlay').removeClass('active');
});

//action button
$('.action-btn .btn').on('click', function (e) {
    e.preventDefault();
    if($(this).siblings('.action-list').hasClass('active')) {
      $(this).siblings('.action-list').removeClass('active');
      $('.body-overlay').removeClass('active');
    }else {
      $(this).siblings('.action-list').addClass('active');
      $('.body-overlay').addClass('active');
    }
  });
  $('#body-overlay').on('click', function (e) {
    e.preventDefault();
    $('.action-list').removeClass('active');
    $('.body-overlay').removeClass('active');
  });

//Profile Upload
function proPicURL(input) {
  if (input.files && input.files[0]) {
      var reader = new FileReader();
      reader.onload = function (e) {
          var preview = $(input).parents('.preview-thumb').find('.profilePicPreview');
          $(preview).css('background-image', 'url(' + e.target.result + ')');
          $(preview).addClass('has-image');
          $(preview).hide();
          $(preview).fadeIn(650);
      }
      reader.readAsDataURL(input.files[0]);
  }
}
$(".profilePicUpload").on('change', function () {
  proPicURL(this);
});

$(".remove-image").on('click', function () {
  $(".profilePicPreview").css('background-image', 'none');
  $(".profilePicPreview").removeClass('has-image');
});

// password
$(document).ready(function() {
  $(".show_hide_password .show-pass").on('click', function(event) {
      event.preventDefault();
      if($(this).parent().find("input").attr("type") == "text"){
          $(this).parent().find("input").attr('type', 'password');
          $(this).find("i").addClass( "fa-eye-slash" );
          $(this).find("i").removeClass( "fa-eye" );
      }else if($(this).parent().find("input").attr("type") == "password"){
          $(this).parent().find("input").attr('type', 'text');
          $(this).find("i").removeClass( "fa-eye-slash" );
          $(this).find("i").addClass( "fa-eye" );
      }
  });
});


$("form button[type=submit], form input[type=submit]").on("click", function (event) {
    var inputFileds = $(this).parents("form").find("input[type=text], input[type=number], input[type=email], input[type=password]");
    var mode = false;
    $.each(inputFileds, function (index, item) {
        if ($(item).attr("required") != undefined) {
            if ($(item).val() == "") {
                mode = true;
            }
        }
    });
    if (mode == false) {
        $(this).parents("form").find(".btn-ring").show();
        $(this).parents("form").find("button[type=submit],input[type=submit]").prop("disabled", true);
        $(this).parents("form").submit();
    }
});

$(document).ready(function () {
    $.each($(".btn-loading"), function (index, item) {
        $(item).append(`<span class="btn-ring"></span>`);
    });
});

// switch
$(document).ready(function(){
    $.each($(".switch-toggles"),function(index,item) {
      var firstSwitch = $(item).find(".switch").first();
      var lastSwitch = $(item).find(".switch").last();
      if(firstSwitch.attr('data-value') == null) {
        $(item).find(".switch").first().attr("data-value",true);
        $(item).find(".switch").last().attr("data-value",false);
      }
      if($(item).hasClass("active")) {
        $(item).find('input').val(firstSwitch.attr("data-value"));
      }else {
        $(item).find('input').val(lastSwitch.attr("data-value"));
      }
    });
  });

  $('.switch-toggles .switch').on('click', function () {
    $(this).parents(".switch-toggles").toggleClass('active');
    $(this).parents(".switch-toggles").find("input").val($(this).attr("data-value"));
  });

})(jQuery);


/**
 * Function For Get All Country list by AJAX Request
 * @param {HTML DOM} targetElement
 * @param {Error Place Element} errorElement
 * @returns
 */
var allCountries = "";
function getAllCountries(hitUrl,targetElement = $(".country-select"),errorElement = $(".country-select").siblings(".select2")) {
  if(targetElement.length == 0) {
    return false;
  }
  var CSRF = $("meta[name=csrf-token]").attr("content");
  var data = {
    _token      : CSRF,
  };
  $.post(hitUrl,data,function() {
    // success
    $(errorElement).removeClass("is-invalid");
    $(targetElement).siblings(".invalid-feedback").remove();
  }).done(function(response){
    // Place States to States Field
    var options = "<option selected disabled>Select Country</option>";
    var selected_old_data = "";
    if($(targetElement).attr("data-old") != null) {
        selected_old_data = $(targetElement).attr("data-old");
    }
    $.each(response,function(index,item) {
        options += `<option value="${item.name}" data-id="${item.id}" data-mobile-code="${item.mobile_code}" ${selected_old_data == item.name ? "selected" : ""}>${item.name}</option>`;
    });

    allCountries = response;

    $(targetElement).html(options);
  }).fail(function(response) {
    var faildMessage = "Something Went Wrong! Please Try Again.";
    var faildElement = `<span class="invalid-feedback" role="alert">
                            <strong>${faildMessage}</strong>
                        </span>`;
    $(errorElement).addClass("is-invalid");
    if($(targetElement).siblings(".invalid-feedback").length != 0) {
        $(targetElement).siblings(".invalid-feedback").text(faildMessage);
    }else {
      errorElement.after(faildElement);
    }
  });
}
// getAllCountries();


/**
 * Function for reload the all countries that already loaded by using getAllCountries() function.
 * @param {string} targetElement
 * @param {string} errorElement
 * @returns
 */
function reloadAllCountries(targetElement,errorElement = $(".country-select").siblings(".select2")) {
  if(allCountries == "" || allCountries == null) {
  // alert();
  return false;
  }
  var options = "<option selected disabled>Select Country</option>";
  var selected_old_data = "";
  if($(targetElement).attr("data-old") != null) {
    selected_old_data = $(targetElement).attr("data-old");
  }
  $.each(allCountries,function(index,item) {
    options += `<option value="${item.name}" data-id="${item.id}" data-currency-name="${item.currency_name}" data-currency-code="${item.currency_code}" data-currency-symbol="${item.currency_symbol}" ${selected_old_data == item.name ? "selected" : ""}>${item.name}</option>`;
  });
  $(targetElement).html(options);
}

function placePhoneCode(code) {
    if(code != undefined) {
        code = code.replace("+","");
        code = "+" + code;
        $("input.phone-code").val(code);
        $("div.phone-code").html(code);
    }
}




function openModalByContent(data = {
content:"",
animation: "mfp-move-horizontal",
size: "medium",
}) {
$.magnificPopup.open({
    removalDelay: 500,
    items: {
    src: `<div class="white-popup mfp-with-anim ${data.size ?? "medium"}">${data.content}</div>`, // can be a HTML string, jQuery object, or CSS selector
    },
    callbacks: {
    beforeOpen: function() {
        this.st.mainClass = data.animation ?? "mfp-move-horizontal";
    },
    open: function() {
        var modalCloseBtn = this.contentContainer.find(".modal-close");
        $(modalCloseBtn).click(function() {
        $.magnificPopup.close();
        });
    },
    },
    midClick: true,
});
}

$(document).ready(function() {
    $(".show_hide_password .show-pass").on('click', function(event) {
        event.preventDefault();
        if($(this).parent().find("input").attr("type") == "text"){
            $(this).parent().find("input").attr('type', 'password');
            $(this).find("i").addClass( "fa-eye-slash" );
            $(this).find("i").removeClass( "fa-eye" );
        }else if($(this).parent().find("input").attr("type") == "password"){
            $(this).parent().find("input").attr('type', 'text');
            $(this).find("i").removeClass( "fa-eye-slash" );
            $(this).find("i").addClass( "fa-eye" );
        }
    });
});


function copyToClipBoard(copyId) {
    var copyText = document.getElementById(copyId);
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);

    notification('success', 'URL Copied To Clipboard!');
}

function switcherAjax(hitUrl,method = "PUT") {
    $(document).on("click",".event-ready",function(event) {
      var inputName = $(this).parents(".switch-toggles").find("input").attr("name");
      if(inputName == undefined || inputName == "") {
        return false;
      }
      $(this).parents(".switch-toggles").find(".switch").removeClass("event-ready");
      var input = $(this).parents(".switch-toggles").find("input[name="+inputName+"]");
      var eventElement = $(this);
      if(input.length == 0) {
          alert("Input field not found.");
          $(this).parents(".switch-toggles").find(".switch").addClass("event-ready");
          $(this).find(".btn-ring").hide();
          return false;
      }
      var CSRF = $("head meta[name=csrf-token]").attr("content");
      var dataTarget = "";
      if(input.attr("data-target")) {
          dataTarget = input.attr("data-target");
      }
      var inputValue = input.val();
      var data = {
        _token: CSRF,
        _method: method,
        data_target: dataTarget,
        status: inputValue,
        input_name: inputName,
      };
      $.post(hitUrl,data,function(response) {
        throwMessage('success',response.message.success);
        // Remove Loading animation
        $(event.target).find(".btn-ring").hide();
      }).done(function(response){
        $(eventElement).parents(".switch-toggles").find(".switch").addClass("event-ready");
        var dataValue = $(eventElement).parents(".switch-toggles").find(".switch").last().attr("data-value");
        if($(eventElement).parents(".switch-toggles").hasClass("active")) {
          dataValue = $(eventElement).parents(".switch-toggles").find(".switch").first().attr("data-value");
        }
        $(eventElement).parents(".switch-toggles.btn-load").find("input").val(dataValue);
      }).fail(function(response) {
          var response = JSON.parse(response.responseText);
          throwMessage(response.type,response.message.error);
          $(eventElement).parents(".switch-toggles").find(".switch").addClass("event-ready");
          $(eventElement).parents(".switch-toggles").find(".btn-ring").hide();
          return false;
      });
    });
  }
