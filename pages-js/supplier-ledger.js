$(document).ready(function () {
    $('#preloader').fadeOut('slow');
    loadSuppliers();
    $('#supplier_id, #fromDate, #toDate').on('change', loadLedger);
    $('#refreshLedgerBtn').on('click', loadLedger);

    function loadSuppliers(){
        $.ajax({url:window.BASE_URL+'api/supplier-ledger.php',type:'GET',dataType:'json',data:{action:'get_suppliers'},success:function(res){
            let html='<option value="">Select Supplier</option>';
            if(res.status) $.each(res.data.suppliers||[],function(_,s){let sel=parseInt(s.id)===parseInt(window.PRE_SUPPLIER_ID||0)?'selected':'';html+=`<option value="${s.id}" ${sel}>${escapeHtml(s.supplier_name||'')}</option>`;});
            $('#supplier_id').html(html);
            if(parseInt(window.PRE_SUPPLIER_ID||0)>0) loadLedger();
        },error:function(xhr){console.log(xhr.responseText);}});
    }
    function loadLedger(){
        let supplierId=parseInt($('#supplier_id').val()||0);
        if(supplierId<=0){$('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">Select supplier.</td></tr>');renderSummary({});return;}
        $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>');
        $.ajax({url:window.BASE_URL+'api/supplier-ledger.php',type:'GET',dataType:'json',data:{action:'list_ledger',supplier_id:supplierId,from_date:$('#fromDate').val(),to_date:$('#toDate').val()},success:function(res){
            if(res.status){renderRows(res.data.entries||[]);renderSummary(res.data.summary||{});}
            else $('#ledgerTableBody').html(`<tr><td colspan="8" class="text-center text-danger">${escapeHtml(res.message||'Unable to load ledger.')}</td></tr>`);
        },error:function(xhr){console.log(xhr.responseText);$('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Server error.</td></tr>');}});
    }
    function renderRows(rows){
        if(!rows||rows.length===0){$('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">No ledger entries found.</td></tr>');return;}
        let html=''; $.each(rows,function(i,r){let b=parseFloat(r.balance||0);let bt=numberFormat(Math.abs(b))+(b>=0?' Cr':' Dr');html+=`<tr><td>${i+1}</td><td>${escapeHtml(r.display_date||r.entry_date||'')}</td><td>${escapeHtml(r.particular||'')}</td><td>${escapeHtml(r.reference_no||'-')}</td><td>${escapeHtml(r.entry_type||'')}</td><td class="text-end">₹${numberFormat(r.debit||0)}</td><td class="text-end">₹${numberFormat(r.credit||0)}</td><td class="text-end fw-semibold">₹${bt}</td></tr>`;});
        $('#ledgerTableBody').html(html);
    }
    function renderSummary(s){$('#totalEntries').text(s.total_entries||0);$('#totalDebit').text(numberFormat(s.total_debit||0));$('#totalCredit').text(numberFormat(s.total_credit||0));let c=parseFloat(s.closing_balance||0);$('#closingBalance').text(numberFormat(Math.abs(c))+(c>=0?' Cr':' Dr'));}
    function numberFormat(v){return parseFloat(v||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});}
    function escapeHtml(v){return $('<div>').text(v===null||v===undefined?'':v).html();}
});