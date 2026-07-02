$(document).ready(function () {

    loadCustomers();

    $('#filterBtn').on('click', function () {
        loadLedger();
    });

    function loadLedger() {

        let types = [];
        $('.docType:checked').each(function () {
            types.push($(this).val());
        });

        $.ajax({
            url: BASE_URL + "api/customer-ledger.php",
            type: "GET",
            data: {
                action: "list",
                customer_id: $('#customerId').val(),
                from: $('#fromDate').val(),
                to: $('#toDate').val(),
                types: types
            },
            success: function (res) {

                let html = "";
                let balance = 0;

                if (res.status) {
                    res.data.forEach(r => {

                        html += `
                        <tr>
                            <td>${r.date}</td>
                            <td>${r.particular}</td>
                            <td class="text-end">${r.debit}</td>
                            <td class="text-end">${r.credit}</td>
                            <td class="text-end">${r.balance}</td>
                        </tr>`;
                    });
                }

                $('#ledgerBody').html(html);
            }
        });
    }

    function loadCustomers() {
        $.get(BASE_URL + "api/customers.php?action=list_customers", function (res) {

            let html = `<option value="">Select</option>`;

            res.data.customers.forEach(c => {
                html += `<option value="${c.id}">${c.customer_name}</option>`;
            });

            $('#customerId').html(html);
        });
    }

});