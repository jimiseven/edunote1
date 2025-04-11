const hamBurger = document.querySelector(".toggle-btn");

hamBurger.addEventListener("click", function () {
  document.querySelector("#sidebar").classList.toggle("expand");
});

document.addEventListener('DOMContentLoaded', function () {
    var collapseElement = document.querySelector('#auth');
    var bsCollapse = new bootstrap.Collapse(collapseElement, {toggle: false});

    document.querySelector('[data-bs-target="#auth"]').addEventListener('click', function (e) {
        e.preventDefault();
        bsCollapse.toggle();
    });
});

