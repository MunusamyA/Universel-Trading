$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let pageContext = {
        can_view: false,
        can_list: false,
        can_print: false,
        can_export: false,
        page_title: 'Supplier Ledger',
        page_note: 'Supplier debit / credit / balance statement'
    };

    let currentSupplier = {};
    let currentEntries = [];
    let currentSummary = {};

    loadPageContext();

    $('#supplier_id, #fromDate, #toDate').on('change', loadLedger);
    $('#refreshLedgerBtn').on('click', loadLedger);

    $(document).on('click', '.print-ledger-option', function () {
        let size = $(this).data('size') || 'a4';
        let orientation = $(this).data('orientation') || 'landscape';
        printLedgerStatement(size, orientation);
    });

    $(document).on('click', '.pdf-ledger-option', function () {
        let orientation = $(this).data('orientation') || 'portrait';
        exportLedgerPdf(orientation);
    });

    $('#exportExcelBtn').on('click', function () {
        exportLedgerExcel();
    });

    $('#exportCsvBtn').on('click', function () {
        exportLedgerCsv();
    });

    function loadPageContext() {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_page_context'
            },
            success: function (response) {
                if (response.status === true) {
                    pageContext = response.data.context || pageContext;
                    applyPageContext();
                    loadSuppliers();
                } else {
                    $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#ledgerFilterCard').addClass('d-none');
                    hidePrintExportButtons();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Server error.</td></tr>');
                $('#ledgerFilterCard').addClass('d-none');
                hidePrintExportButtons();
            }
        });
    }

    function applyPageContext() {
        $('#pageTitleText').text(pageContext.page_title || 'Supplier Ledger');
        $('#pageNoteText').text(pageContext.page_note || '');

        if (pageContext.can_print) {
            $('#printLedgerDropdown').removeClass('d-none');
        } else {
            $('#printLedgerDropdown').addClass('d-none');
        }

        if (canExportLedger()) {
            $('#exportExcelBtn, #exportPdfDropdown, #exportCsvBtn').removeClass('d-none');
        } else {
            $('#exportExcelBtn, #exportPdfDropdown, #exportCsvBtn').addClass('d-none');
        }
    }

    function hidePrintExportButtons() {
        $('#printLedgerDropdown, #exportExcelBtn, #exportPdfDropdown, #exportCsvBtn').addClass('d-none');
    }

    function canExportLedger() {
        return pageContext.can_export === true
            || pageContext.can_export === 1
            || pageContext.can_export === '1'
            || pageContext.can_print === true
            || pageContext.can_print === 1
            || pageContext.can_print === '1';
    }

    function loadSuppliers() {
        if (!pageContext.can_view && !pageContext.can_list) {
            $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_suppliers'
            },
            success: function (response) {
                let html = '<option value="">Select Supplier</option>';

                if (response.status === true) {
                    $.each(response.data.suppliers || [], function (_, supplier) {
                        let selected = parseInt(supplier.id) === parseInt(window.PRE_SUPPLIER_ID || 0) ? 'selected' : '';
                        html += '<option value="' + supplier.id + '" ' + selected + '>' + escapeHtml(supplier.supplier_name || '') + '</option>';
                    });
                }

                $('#supplier_id').html(html);

                if (parseInt(window.PRE_SUPPLIER_ID || 0) > 0) {
                    loadLedger();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function loadLedger() {
        if (!pageContext.can_list) {
            $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        let supplierId = parseInt($('#supplier_id').val() || 0);

        if (supplierId <= 0) {
            currentSupplier = {};
            currentEntries = [];
            currentSummary = {};
            $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">Select supplier.</td></tr>');
            renderSummary({});
            renderPrintReport('a4', 'landscape');
            return;
        }

        $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_ledger',
                supplier_id: supplierId,
                from_date: $('#fromDate').val(),
                to_date: $('#toDate').val()
            },
            success: function (response) {
                if (response.status === true) {
                    currentSupplier = response.data.supplier || {};
                    currentEntries = response.data.entries || [];
                    currentSummary = response.data.summary || {};
                    renderRows(currentEntries);
                    renderSummary(currentSummary);
                    renderPrintReport('a4', 'landscape');
                } else {
                    currentEntries = [];
                    currentSummary = {};
                    $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load ledger.') + '</td></tr>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderRows(rows) {
        if (!rows || rows.length === 0) {
            $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">No ledger entries found.</td></tr>');
            return;
        }

        let html = '';

        $.each(rows, function (index, row) {
            let balance = parseFloat(row.balance || 0);
            let balanceText = numberFormat(Math.abs(balance)) + (balance >= 0 ? ' Cr' : ' Dr');

            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td>' + escapeHtml(displayDate(row)) + '</td>';
            html += '<td>' + escapeHtml(row.particular || '') + '</td>';
            html += '<td>' + escapeHtml(row.reference_no || '-') + '</td>';
            html += '<td>' + escapeHtml(row.entry_type || '') + '</td>';
            html += '<td class="text-end">₹' + numberFormat(row.debit || 0) + '</td>';
            html += '<td class="text-end">₹' + numberFormat(row.credit || 0) + '</td>';
            html += '<td class="text-end fw-semibold">₹' + balanceText + '</td>';
            html += '</tr>';
        });

        $('#ledgerTableBody').html(html);
    }

    function renderSummary(summary) {
        $('#totalEntries').text(summary.total_entries || 0);
        $('#totalDebit').text(numberFormat(summary.total_debit || 0));
        $('#totalCredit').text(numberFormat(summary.total_credit || 0));

        let closing = parseFloat(summary.closing_balance || 0);
        $('#closingBalance').text(numberFormat(Math.abs(closing)) + (closing >= 0 ? ' Cr' : ' Dr'));
    }

    function printLedgerStatement(format, orientation) {
        if (!pageContext.can_print) {
            showLedgerToast('error', 'Permission denied.');
            return;
        }

        if (!ensureLedgerLoaded()) {
            return;
        }

        format = format === 'a3' ? 'a3' : 'a4';
        orientation = orientation === 'portrait' ? 'portrait' : 'landscape';
        preparePrintFormat(format, orientation);
        renderPrintReport(format, orientation);

        setTimeout(function () {
            window.print();
        }, 80);
    }

    function exportLedgerPdf(orientation) {
        if (!canExportLedger()) {
            showLedgerToast('error', 'Permission denied.');
            return;
        }

        if (!ensureLedgerLoaded()) {
            return;
        }

        downloadLedgerPdf(orientation);
    }

    function downloadLedgerPdf(orientation) {
        orientation = orientation === 'portrait' ? 'portrait' : 'landscape';
        let pdfContent = buildLedgerPdfContent(orientation);
        downloadBlob(pdfContent, exportFileName('pdf', orientation), 'application/pdf');
        showLedgerToast('success', 'PDF ' + orientation + ' downloaded successfully.');
    }

    function buildLedgerPdfContent(orientation) {
        orientation = orientation === 'portrait' ? 'portrait' : 'landscape';

        // A4 sizes in PDF points: portrait 595.28 x 841.89, landscape 841.89 x 595.28.
        let pageWidth = orientation === 'portrait' ? 595.28 : 841.89;
        let pageHeight = orientation === 'portrait' ? 841.89 : 595.28;
        let margin = orientation === 'portrait' ? 24 : 28;
        let usableWidth = pageWidth - (margin * 2);
        let rows = currentEntries || [];
        let supplierName = selectedSupplierName() || '-';
        let branchName = window.BRANCH_NAME || 'Branch';
        let periodText = getPeriodText();
        let generatedAt = new Date().toLocaleString('en-IN');
        let closing = parseFloat(currentSummary.closing_balance || 0);
        let closingText = 'Rs. ' + numberFormat(Math.abs(closing)) + (closing >= 0 ? ' Cr' : ' Dr');
        let pages = [];
        let page = null;
        let y = 0;
        let tableLeft = margin;
        let colWidths = buildPdfColumnWidths(orientation, usableWidth);
        let colX = [];
        let tableWidth = colWidths.reduce(function (sum, width) { return sum + width; }, 0);
        let headerFont = orientation === 'portrait' ? 14 : 16;
        let rowFont = orientation === 'portrait' ? 7 : 8;
        let headerRowHeight = orientation === 'portrait' ? 20 : 22;
        let lineHeight = orientation === 'portrait' ? 8 : 9;
        let footerLimit = orientation === 'portrait' ? 36 : 34;

        function buildPdfColumnWidths(format, width) {
            let ratios = format === 'portrait'
                ? [0.055, 0.115, 0.275, 0.145, 0.105, 0.10, 0.10, 0.105]
                : [0.040, 0.095, 0.285, 0.145, 0.115, 0.115, 0.115, 0.090];
            let widths = ratios.map(function (ratio) { return Math.floor(width * ratio); });
            let diff = width - widths.reduce(function (sum, item) { return sum + item; }, 0);
            widths[2] += diff;
            return widths;
        }

        function addPage() {
            page = [];
            pages.push(page);
            y = pageHeight - margin;

            pdfText(page, branchName.toUpperCase(), margin, y, headerFont, 'F2');
            pdfText(page, 'Supplier Ledger Statement', margin, y - 18, 10, 'F1');
            pdfTextRight(page, 'Generated: ' + generatedAt, pageWidth - margin, y, 8, 'F1');
            pdfTextRight(page, 'Period: ' + periodText, pageWidth - margin, y - 13, 8, 'F1');
            pdfLine(page, margin, y - 28, pageWidth - margin, y - 28);
            y -= 44;

            pdfText(page, 'Supplier: ' + supplierName, margin, y, 9, 'F2');
            pdfTextRight(page, 'Format: A4 ' + (orientation === 'portrait' ? 'Portrait' : 'Landscape') + ' PDF Download', pageWidth - margin, y, 8, 'F1');
            y -= 18;

            drawKpiCards();
            drawTableHeader();
        }

        function drawKpiCards() {
            let values = [
                ['Entries', String(currentSummary.total_entries || rows.length || 0)],
                ['Total Paid', 'Rs. ' + numberFormat(currentSummary.total_debit || 0)],
                ['Total Purchase', 'Rs. ' + numberFormat(currentSummary.total_credit || 0)],
                ['Closing Balance', closingText]
            ];
            let columns = orientation === 'portrait' ? 2 : 4;
            let gap = 8;
            let cardWidth = (usableWidth - (gap * (columns - 1))) / columns;
            let cardHeight = orientation === 'portrait' ? 34 : 38;
            let startY = y;

            $.each(values, function (index, item) {
                let col = index % columns;
                let row = Math.floor(index / columns);
                let x = margin + (col * (cardWidth + gap));
                let topY = startY - (row * (cardHeight + 7));
                drawKpi(item[0], item[1], x, topY, cardWidth, cardHeight);
            });

            y -= orientation === 'portrait' ? 86 : 54;
        }

        function drawKpi(label, value, x, topY, width, height) {
            pdfRect(page, x, topY - height, width, height, true);
            pdfText(page, label, x + 7, topY - 12, 7, 'F1');
            pdfText(page, value, x + 7, topY - 26, orientation === 'portrait' ? 9 : 10, 'F2');
        }

        function drawTableHeader() {
            colX = [];
            let x = tableLeft;
            for (let i = 0; i < colWidths.length; i++) {
                colX.push(x);
                x += colWidths[i];
            }

            pdfRect(page, tableLeft, y - headerRowHeight, tableWidth, headerRowHeight, true);
            let headers = ['#', 'Date', 'Particular', 'Reference', 'Type', 'Debit', 'Credit', 'Balance'];
            for (let i = 0; i < headers.length; i++) {
                pdfRect(page, colX[i], y - headerRowHeight, colWidths[i], headerRowHeight, false);
                pdfText(page, headers[i], colX[i] + 4, y - 13, rowFont, 'F2');
            }
            y -= headerRowHeight;
        }

        function drawFooter(pageIndex) {
            pdfLine(page, margin, 22, pageWidth - margin, 22);
            pdfText(page, 'This is a system generated supplier ledger statement.', margin, 11, 7, 'F1');
            pdfTextRight(page, 'Printed by ' + branchName + ' | Page ' + pageIndex, pageWidth - margin, 11, 7, 'F1');
        }

        addPage();

        if (!rows.length) {
            drawPdfRow(['', '', 'No ledger entries found.', '', '', '', '', ''], orientation === 'portrait' ? 17 : 18);
        } else {
            $.each(rows, function (index, row) {
                let balance = parseFloat(row.balance || 0);
                let rowData = [
                    String(index + 1),
                    displayDate(row),
                    row.particular || '-',
                    row.reference_no || '-',
                    row.entry_type || '-',
                    'Rs. ' + numberFormat(row.debit || 0),
                    'Rs. ' + numberFormat(row.credit || 0),
                    'Rs. ' + numberFormat(Math.abs(balance)) + (balance >= 0 ? ' Cr' : ' Dr')
                ];
                drawPdfRow(rowData);
            });
        }

        for (let i = 0; i < pages.length; i++) {
            page = pages[i];
            drawFooter(i + 1);
        }

        return assemblePdf(pages, pageWidth, pageHeight);

        function drawPdfRow(rowData, forcedHeight) {
            let maxCharSets = orientation === 'portrait'
                ? [4, 10, 30, 16, 13, 12, 12, 13]
                : [4, 12, 42, 20, 16, 16, 16, 17];

            let wrappedCells = rowData.map(function (cell, cellIndex) {
                let maxChars = maxCharSets[cellIndex] || 14;
                return wrapPdfText(cell, maxChars, cellIndex === 2 ? 2 : 1);
            });
            let rowHeight = forcedHeight || Math.max(orientation === 'portrait' ? 17 : 18, wrappedCells.reduce(function (max, lines) {
                return Math.max(max, (lines.length * lineHeight) + 8);
            }, orientation === 'portrait' ? 17 : 18));

            if (y - rowHeight < footerLimit) {
                drawFooter(pages.length);
                addPage();
            }

            for (let i = 0; i < colWidths.length; i++) {
                pdfRect(page, colX[i], y - rowHeight, colWidths[i], rowHeight, false);
                let lines = wrappedCells[i];
                for (let lineIndex = 0; lineIndex < lines.length; lineIndex++) {
                    let textY = y - 11 - (lineIndex * lineHeight);
                    if (i >= 5) {
                        pdfTextRight(page, lines[lineIndex], colX[i] + colWidths[i] - 4, textY, rowFont, i === 7 ? 'F2' : 'F1');
                    } else {
                        pdfText(page, lines[lineIndex], colX[i] + 4, textY, rowFont, i === 2 ? 'F2' : 'F1');
                    }
                }
            }
            y -= rowHeight;
        }
    }

    function assemblePdf(pages, pageWidth, pageHeight) {
        let objects = [];
        let pageObjectIds = [];
        let catalogId = addObject('');
        let pagesId = addObject('');
        let fontId = addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        let boldFontId = addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');

        $.each(pages, function (_, commands) {
            let stream = commands.join('\n');
            let contentId = addObject('<< /Length ' + byteLength(stream) + ' >>\nstream\n' + stream + '\nendstream');
            let pageId = addObject(
                '<< /Type /Page /Parent ' + pagesId + ' 0 R /MediaBox [0 0 ' + pageWidth.toFixed(2) + ' ' + pageHeight.toFixed(2) + '] ' +
                '/Resources << /Font << /F1 ' + fontId + ' 0 R /F2 ' + boldFontId + ' 0 R >> >> ' +
                '/Contents ' + contentId + ' 0 R >>'
            );
            pageObjectIds.push(pageId);
        });

        objects[catalogId - 1] = '<< /Type /Catalog /Pages ' + pagesId + ' 0 R >>';
        objects[pagesId - 1] = '<< /Type /Pages /Kids [' + pageObjectIds.map(function (id) { return id + ' 0 R'; }).join(' ') + '] /Count ' + pageObjectIds.length + ' >>';

        let pdf = '%PDF-1.4\n';
        let offsets = [0];
        for (let i = 0; i < objects.length; i++) {
            offsets.push(byteLength(pdf));
            pdf += (i + 1) + ' 0 obj\n' + objects[i] + '\nendobj\n';
        }
        let xrefOffset = byteLength(pdf);
        pdf += 'xref\n0 ' + (objects.length + 1) + '\n';
        pdf += '0000000000 65535 f \n';
        for (let j = 1; j < offsets.length; j++) {
            pdf += String(offsets[j]).padStart(10, '0') + ' 00000 n \n';
        }
        pdf += 'trailer\n<< /Size ' + (objects.length + 1) + ' /Root ' + catalogId + ' 0 R >>\n';
        pdf += 'startxref\n' + xrefOffset + '\n%%EOF';
        return pdf;

        function addObject(value) {
            objects.push(value);
            return objects.length;
        }
    }

    function pdfText(commands, text, x, y, size, font) {
        commands.push('BT /' + (font || 'F1') + ' ' + size + ' Tf ' + x.toFixed(2) + ' ' + y.toFixed(2) + ' Td (' + pdfEscape(text) + ') Tj ET');
    }

    function pdfTextRight(commands, text, x, y, size, font) {
        text = pdfSafeText(text);
        let estimatedWidth = text.length * size * 0.48;
        pdfText(commands, text, x - estimatedWidth, y, size, font);
    }

    function pdfLine(commands, x1, y1, x2, y2) {
        commands.push('0 G 0.6 w ' + x1.toFixed(2) + ' ' + y1.toFixed(2) + ' m ' + x2.toFixed(2) + ' ' + y2.toFixed(2) + ' l S');
    }

    function pdfRect(commands, x, y, w, h, fill) {
        if (fill) {
            commands.push('0.94 0.96 0.98 rg ' + x.toFixed(2) + ' ' + y.toFixed(2) + ' ' + w.toFixed(2) + ' ' + h.toFixed(2) + ' re f 0 g');
        }
        commands.push('0.75 0.80 0.86 RG 0.4 w ' + x.toFixed(2) + ' ' + y.toFixed(2) + ' ' + w.toFixed(2) + ' ' + h.toFixed(2) + ' re S 0 G');
    }

    function wrapPdfText(value, maxChars, maxLines) {
        let text = pdfSafeText(value);
        let words = text.split(/\s+/);
        let lines = [];
        let line = '';
        maxLines = maxLines || 1;

        $.each(words, function (_, word) {
            if ((line + ' ' + word).trim().length > maxChars && line !== '') {
                lines.push(line);
                line = word;
            } else {
                line = (line + ' ' + word).trim();
            }
        });
        if (line !== '') lines.push(line);
        if (lines.length === 0) lines.push('-');

        if (lines.length > maxLines) {
            lines = lines.slice(0, maxLines);
            lines[maxLines - 1] = lines[maxLines - 1].slice(0, Math.max(0, maxChars - 3)) + '...';
        }
        return lines;
    }

    function pdfEscape(value) {
        return pdfSafeText(value).replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
    }

    function pdfSafeText(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/₹/g, 'Rs.')
            .replace(/[\u2018\u2019]/g, "'")
            .replace(/[\u201C\u201D]/g, '"')
            .replace(/[\u2013\u2014]/g, '-')
            .replace(/[^\x20-\x7E]/g, '');
    }

    function byteLength(value) {
        return new Blob([value]).size;
    }

    function preparePrintFormat(format, orientation) {
        format = format === 'a3' ? 'a3' : 'a4';
        orientation = orientation === 'portrait' ? 'portrait' : 'landscape';
        let size = (format === 'a3' ? 'A3' : 'A4') + ' ' + orientation;
        let margin = orientation === 'portrait' ? '10mm' : '8mm';

        $('#ledgerPrintPageSize').text('@page { size: ' + size + '; margin: ' + margin + '; }');
        $('#printLedgerFormatText').text(format.toUpperCase() + ' ' + titleCase(orientation));
        $('body')
            .removeClass('ledger-print-a3 ledger-print-a4 ledger-print-portrait ledger-print-landscape')
            .addClass('ledger-print-' + format + ' ledger-print-' + orientation);
    }

    function renderPrintReport(format, orientation) {
        format = format === 'a3' ? 'a3' : 'a4';
        orientation = orientation === 'portrait' ? 'portrait' : 'landscape';
        let supplierName = currentSupplier.supplier_name || selectedSupplierName() || '-';
        let periodText = getPeriodText();
        let totalEntries = currentSummary.total_entries || (currentEntries ? currentEntries.length : 0) || 0;
        let closing = parseFloat(currentSummary.closing_balance || 0);
        let closingText = '₹' + numberFormat(Math.abs(closing)) + (closing >= 0 ? ' Cr' : ' Dr');

        $('#printLedgerFormatText').text(format.toUpperCase() + ' ' + titleCase(orientation));
        $('#printGeneratedAt').text(new Date().toLocaleString('en-IN'));
        $('#printSupplierName').text(supplierName);
        $('#printPeriodText').text(periodText);
        $('#printTotalEntries').text(totalEntries);
        $('#printTotalDebit').text('₹' + numberFormat(currentSummary.total_debit || 0));
        $('#printTotalCredit').text('₹' + numberFormat(currentSummary.total_credit || 0));
        $('#printClosingBalance').text(closingText);
        $('#printKpiEntries').text(totalEntries);
        $('#printKpiDebit').text('₹' + numberFormat(currentSummary.total_debit || 0));
        $('#printKpiCredit').text('₹' + numberFormat(currentSummary.total_credit || 0));
        $('#printKpiClosing').text(closingText);

        if (!currentEntries || currentEntries.length === 0) {
            $('#printLedgerTableBody').html('<tr><td colspan="8" class="print-text-center">No ledger entries found.</td></tr>');
            return;
        }

        let html = '';
        $.each(currentEntries, function (index, row) {
            let balance = parseFloat(row.balance || 0);
            let balanceText = '₹' + numberFormat(Math.abs(balance)) + (balance >= 0 ? ' Cr' : ' Dr');

            html += '<tr>';
            html += '<td class="print-text-center">' + (index + 1) + '</td>';
            html += '<td>' + escapeHtml(displayDate(row)) + '</td>';
            html += '<td>' + escapeHtml(row.particular || '-') + '</td>';
            html += '<td>' + escapeHtml(row.reference_no || '-') + '</td>';
            html += '<td>' + escapeHtml(row.entry_type || '-') + '</td>';
            html += '<td class="print-text-end">₹' + numberFormat(row.debit || 0) + '</td>';
            html += '<td class="print-text-end">₹' + numberFormat(row.credit || 0) + '</td>';
            html += '<td class="print-text-end"><strong>' + balanceText + '</strong></td>';
            html += '</tr>';
        });

        $('#printLedgerTableBody').html(html);
    }

    function exportLedgerCsv() {
        if (!canExportLedger()) {
            showLedgerToast('error', 'Permission denied.');
            return;
        }

        if (!ensureLedgerLoaded()) {
            return;
        }

        let rows = buildExportRows();
        let csv = rows.map(function (row) {
            return row.map(csvCell).join(',');
        }).join('\n');

        downloadBlob('\ufeff' + csv, exportFileName('csv'), 'text/csv;charset=utf-8;');
    }

    function exportLedgerExcel() {
        if (!canExportLedger()) {
            showLedgerToast('error', 'Permission denied.');
            return;
        }

        if (!ensureLedgerLoaded()) {
            return;
        }

        let rows = buildExportRows();
        let html = '<html><head><meta charset="UTF-8"></head><body>';
        html += '<h3>' + escapeHtml(window.BRANCH_NAME || 'Branch') + '</h3>';
        html += '<h4>Supplier Ledger Statement</h4>';
        html += '<p><strong>Supplier:</strong> ' + escapeHtml(selectedSupplierName()) + '</p>';
        html += '<p><strong>Period:</strong> ' + escapeHtml(getPeriodText()) + '</p>';
        html += '<table border="1">';
        $.each(rows, function (rowIndex, row) {
            html += '<tr>';
            $.each(row, function (_, cell) {
                html += (rowIndex === 0 ? '<th>' : '<td>') + escapeHtml(cell) + (rowIndex === 0 ? '</th>' : '</td>');
            });
            html += '</tr>';
        });
        html += '</table></body></html>';

        downloadBlob('\ufeff' + html, exportFileName('xls'), 'application/vnd.ms-excel;charset=utf-8;');
    }

    function buildExportRows() {
        let rows = [[
            '#',
            'Date',
            'Particular',
            'Reference',
            'Type',
            'Debit',
            'Credit',
            'Balance'
        ]];

        $.each(currentEntries || [], function (index, row) {
            let balance = parseFloat(row.balance || 0);
            rows.push([
                String(index + 1),
                displayDate(row),
                row.particular || '',
                row.reference_no || '-',
                row.entry_type || '',
                numberFormat(row.debit || 0),
                numberFormat(row.credit || 0),
                numberFormat(Math.abs(balance)) + (balance >= 0 ? ' Cr' : ' Dr')
            ]);
        });

        rows.push([]);
        rows.push(['', '', '', '', 'Total', numberFormat(currentSummary.total_debit || 0), numberFormat(currentSummary.total_credit || 0), '']);
        let closing = parseFloat(currentSummary.closing_balance || 0);
        rows.push(['', '', '', '', 'Closing Balance', '', '', numberFormat(Math.abs(closing)) + (closing >= 0 ? ' Cr' : ' Dr')]);

        return rows;
    }

    function ensureLedgerLoaded() {
        if (!currentEntries || currentEntries.length === 0) {
            showLedgerToast('warning', 'No ledger entries available to print/export.');
            return false;
        }
        return true;
    }

    function selectedSupplierName() {
        return $('#supplier_id option:selected').text() || currentSupplier.supplier_name || '-';
    }

    function getPeriodText() {
        let from = $('#fromDate').val();
        let to = $('#toDate').val();

        if (from && to) {
            return formatDate(from) + ' to ' + formatDate(to);
        }
        if (from) {
            return 'From ' + formatDate(from);
        }
        if (to) {
            return 'Up to ' + formatDate(to);
        }
        return 'All Dates';
    }

    function exportFileName(extension, suffix) {
        let supplier = sanitizeFileName(selectedSupplierName() || 'supplier');
        let date = new Date().toISOString().slice(0, 10);
        return 'supplier-ledger-' + supplier + (suffix ? '-' + sanitizeFileName(suffix) : '') + '-' + date + '.' + extension;
    }

    function sanitizeFileName(value) {
        value = String(value || 'supplier').toLowerCase().trim();
        value = value.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        return value || 'supplier';
    }

    function csvCell(value) {
        value = String(value === null || value === undefined ? '' : value);
        value = value.replace(/"/g, '""');
        return '"' + value + '"';
    }

    function downloadBlob(content, fileName, mimeType) {
        let blob = new Blob([content], { type: mimeType });
        let url = URL.createObjectURL(blob);
        let link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 1000);
    }

    function displayDate(row) {
        let value = row.display_date || row.entry_date || '';
        if (value === 'Opening') {
            return 'Opening';
        }
        return formatDate(value);
    }

    function formatDate(date) {
        if (!date) return '-';
        let parts = String(date).split('-');
        if (parts.length === 3) return parts[2] + '-' + parts[1] + '-' + parts[0];
        return String(date);
    }

    function titleCase(value) {
        value = String(value || '');
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function numberFormat(value) {
        return parseFloat(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function showLedgerToast(type, message) {
        if (typeof showToast === 'function') {
            showToast(type, message, 5000);
            return;
        }

        if (typeof showAppToast === 'function') {
            showAppToast(type, message);
            return;
        }

        alert(message);
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

});
