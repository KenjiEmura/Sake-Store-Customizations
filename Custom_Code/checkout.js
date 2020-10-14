adult = false;

function isAdult() {
  
  var form = document.getElementById("checkout_form");
  var question = document.getElementById("ask_isAdult");

    adult = true;

    if (adult == true) {
      form.style.display = "block";
      question.style.display = "none";
    } else {
      adult = false;
    }
  }