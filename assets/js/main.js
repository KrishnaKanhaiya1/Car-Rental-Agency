document.addEventListener('DOMContentLoaded', function () {
    var dateInputs = document.querySelectorAll('input[data-min-today="true"]');
    var today = new Date().toISOString().split('T')[0];

    dateInputs.forEach(function (input) {
        input.setAttribute('min', today);
    });

    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alertElement) {
        window.setTimeout(function () {
            var alert = bootstrap.Alert.getOrCreateInstance(alertElement);
            alert.close();
        }, 5000);
    });

    var revealElements = document.querySelectorAll('.reveal');
    if (revealElements.length > 0 && 'IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        revealElements.forEach(function (element) {
            observer.observe(element);
        });
    } else {
        revealElements.forEach(function (element) {
            element.classList.add('visible');
        });
    }

    var rentForms = document.querySelectorAll('.rent-form');
    rentForms.forEach(function (form) {
        var rentPerDay = Number(form.getAttribute('data-rent-per-day') || 0);
        var daysSelect = form.querySelector('select[name="days"]');
        var totalLabel = form.querySelector('.rent-total');

        var updateTotal = function () {
            if (!daysSelect || !totalLabel) {
                return;
            }

            var days = Number(daysSelect.value || 0);
            if (days > 0 && rentPerDay > 0) {
                var total = (days * rentPerDay).toFixed(2);
                totalLabel.textContent = 'Estimated total: Rs ' + total;
            } else {
                totalLabel.textContent = '';
            }
        };

        if (daysSelect) {
            daysSelect.addEventListener('change', updateTotal);
        }

        form.addEventListener('submit', function () {
            var submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';
            }
        });
    });
});
