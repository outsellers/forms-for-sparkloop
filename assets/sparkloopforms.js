addEventListener('DOMContentLoaded', function() {
    const sparkLoopForms = document.querySelectorAll('.sparkloop-forms--form');
    const site_url = window.spl_site_data?.site_url;
    const assets_url = window.spl_site_data?.assets_url;

    if(sparkLoopForms) {
        sparkLoopForms.forEach(sparkLoopForm => {
            sparkLoopForm.addEventListener('submit', (e) => {
                e.preventDefault();
                let count = e.target.getAttribute('data-count');
                let formWrapper = document.getElementById('sparkLoopForm-' + count)

                grecaptcha.ready(function () {
                    grecaptcha.execute('6LeKApUoAAAAAMV8PBabzN_34QHXq31EoV-4fHVm', {action: 'submit'}).then(function (token) {
                        e.preventDefault();
                        const url = site_url + '/wp-json/sparkloopforms/sendgrid/add-contact';
                        const formData = new FormData(sparkLoopForm);
                        const email = formData.get('email');
                        const _wpnonce = document.getElementById('_wpnonce');
                        const nonceValue = spl_site_data.nonce;

                        data = {
                            email: email,
                        }

                        if (formWrapper) {
                            formWrapper.innerHTML = `
                                <div class="sparkloop-forms--loading">
                                    <div class="sparkloop-forms--loading-inner">
                                        <img style="max-width: 30px; height: auto;" src="${assets_url}images/loading.gif">
                                    </div>
                                </div>
                            `;
                        }

                        fetch(url, {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-WP-Nonce": nonceValue,
                            },
                            body: JSON.stringify(data)
                        })
                            .then(response => response.json())
                            .then(data => {
                                let formWrapper = document.getElementById('sparkLoopForm-' + count);
                                let statusClass, statusMessage;

                                console.log("the data")
                                console.log(data)

                                if (data.errors) {
                                    statusClass = "sparkloop-forms--error";
                                    statusMessage = "There has been an error.";
                                    console.log(data.errors[0].message)
                                } else if (data.successfully_added_subscriber) {
                                    statusClass = "sparkloop-forms--success";
                                    statusMessage = "Success! Thank you for signing up";
                                } else {
                                    statusClass = "sparkloop-forms--error";
                                    statusMessage = "Unknown error occurred.";
                                }

                                if (formWrapper) {
                                    formWrapper.innerHTML = `
                                <div class="sparkloop_forms--response">
                                    <p class="${statusClass}">${statusMessage}</p>
                                    <p>${data.message || ""}</p>
                                </div>
                                `;
                                }
                                //
                                // console.log("Success: ", data)
                            })
                            .catch((error) => {
                                console.log("Error: ", error)
                            });
                    })
                })
            });
        });
    }
});

