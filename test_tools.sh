#!/bin/bash
# Barney MCP Tools - Comprehensive Test Script
BASE="http://127.0.0.1:8000/barney"
PASS=0
FAIL=0
RESULTS=""

call_tool() {
    local name="$1"
    local args="$2"
    curl -s -X POST "$BASE" \
        -H "Content-Type: application/json" \
        -d "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/call\",\"params\":{\"name\":\"$name\",\"arguments\":$args}}"
}

check() {
    local test_name="$1"
    local response="$2"
    local expect="$3"
    local is_error="${4:-false}"

    # Normalize: unescape \\n to actual newlines, remove extra escaping
    local normalized
    normalized=$(echo "$response" | sed 's/\\n/\n/g' | sed 's/\\"/"/g')

    if [ "$is_error" = "true" ]; then
        if echo "$normalized" | grep -q "isError.*true"; then
            PASS=$((PASS+1))
            RESULTS+="PASS | $test_name\n"
        else
            FAIL=$((FAIL+1))
            RESULTS+="FAIL | $test_name | Expected error but got: $(echo $response | head -c 200)\n"
        fi
    else
        if echo "$normalized" | grep -q "$expect"; then
            PASS=$((PASS+1))
            RESULTS+="PASS | $test_name\n"
        else
            FAIL=$((FAIL+1))
            RESULTS+="FAIL | $test_name | Expected '$expect' in: $(echo $response | head -c 300)\n"
        fi
    fi
}

echo "============================================"
echo "BARNEY MCP TOOLS - TEST SUITE"
echo "============================================"

# ---- 1. GET PROFILE (empty) ----
echo "[1/40] get_profile - no profile yet..."
R=$(call_tool "get_profile" "{}")
check "get_profile (empty)" "$R" "isError" "true"

# ---- 2. UPDATE PROFILE - missing required fields ----
echo "[2/40] update_profile - missing required..."
R=$(call_tool "update_profile" '{"city":"Jaipur"}')
check "update_profile (missing required)" "$R" "isError" "true"

# ---- 3. UPDATE PROFILE - create ----
echo "[3/40] update_profile - create profile..."
R=$(call_tool "update_profile" '{"first_name":"Manish","last_name":"Jangid","date_of_birth":"1995-06-15","email":"manish@example.com","phone":"9876543210","city":"Jaipur","state":"Rajasthan"}')
check "update_profile (create)" "$R" "Profile updated"

# ---- 4. GET PROFILE (exists) ----
echo "[4/40] get_profile - should return profile..."
R=$(call_tool "get_profile" "{}")
check "get_profile (exists)" "$R" "Manish"

# ---- 5. UPDATE PROFILE - partial update ----
echo "[5/40] update_profile - partial update..."
R=$(call_tool "update_profile" '{"city":"Bangalore","phone":"9988776655"}')
check "update_profile (partial)" "$R" "Bangalore"

# ---- 6. GET PREFERENCES (empty) ----
echo "[6/40] get_preferences - empty..."
R=$(call_tool "get_preferences" "{}")
check "get_preferences (empty)" "$R" "\"count\": 0"

# ---- 7. MANAGE PREFERENCE - add ----
echo "[7/40] manage_preference - add..."
R=$(call_tool "manage_preference" '{"action":"add","key":"default_account","instruction":"Use HDFC for daily expenses"}')
check "manage_preference (add)" "$R" "Preference added"

# ---- 8. MANAGE PREFERENCE - add second ----
echo "[8/40] manage_preference - add second..."
R=$(call_tool "manage_preference" '{"action":"add","key":"food_rule","instruction":"Swiggy and Zomato are food not groceries"}')
check "manage_preference (add 2nd)" "$R" "\"total\": 2"

# ---- 9. MANAGE PREFERENCE - duplicate key ----
echo "[9/40] manage_preference - duplicate key..."
R=$(call_tool "manage_preference" '{"action":"add","key":"default_account","instruction":"duplicate"}')
check "manage_preference (duplicate)" "$R" "isError" "true"

# ---- 10. MANAGE PREFERENCE - update ----
echo "[10/40] manage_preference - update..."
R=$(call_tool "manage_preference" '{"action":"update","key":"food_rule","instruction":"Swiggy, Zomato, and Blinkit are food"}')
check "manage_preference (update)" "$R" "Preference updated"

# ---- 11. GET PREFERENCES (2 items) ----
echo "[11/40] get_preferences - 2 items..."
R=$(call_tool "get_preferences" "{}")
check "get_preferences (2 items)" "$R" "\"count\": 2"

# ---- 12. MANAGE PREFERENCE - remove ----
echo "[12/40] manage_preference - remove..."
R=$(call_tool "manage_preference" '{"action":"remove","key":"food_rule"}')
check "manage_preference (remove)" "$R" "Preference removed"

# ---- 13. MANAGE PREFERENCE - remove nonexistent ----
echo "[13/40] manage_preference - remove nonexistent..."
R=$(call_tool "manage_preference" '{"action":"remove","key":"nonexistent"}')
check "manage_preference (not found)" "$R" "isError" "true"

# ---- 14. MANAGE PREFERENCE - add back for final data ----
echo "[14/40] manage_preference - re-add food_rule..."
R=$(call_tool "manage_preference" '{"action":"add","key":"food_rule","instruction":"Swiggy and Zomato are food not groceries"}')
check "manage_preference (re-add)" "$R" "Preference added"

# ---- 15. LIST ACCOUNTS (empty) ----
echo "[15/40] list_accounts - empty..."
R=$(call_tool "list_accounts" "{}")
check "list_accounts (empty)" "$R" "\"total_balance\": 0"

# ---- 16. MANAGE ACCOUNT - create HDFC ----
echo "[16/40] manage_account - create HDFC..."
R=$(call_tool "manage_account" '{"action":"create","name":"HDFC Savings","type":"bank","bank_name":"HDFC","account_number":"***4521","balance":50000}')
check "manage_account (create HDFC)" "$R" "Account created"

# ---- 17. MANAGE ACCOUNT - create Cash ----
echo "[17/40] manage_account - create Cash..."
R=$(call_tool "manage_account" '{"action":"create","name":"Cash","type":"cash","balance":3000}')
check "manage_account (create Cash)" "$R" "Account created"

# ---- 18. MANAGE ACCOUNT - create SBI ----
echo "[18/40] manage_account - create SBI..."
R=$(call_tool "manage_account" '{"action":"create","name":"SBI Salary","type":"bank","bank_name":"SBI","account_number":"***8832","balance":120000}')
check "manage_account (create SBI)" "$R" "Account created"

# ---- 19. MANAGE ACCOUNT - missing fields ----
echo "[19/40] manage_account - missing fields..."
R=$(call_tool "manage_account" '{"action":"create","name":"Bad"}')
check "manage_account (missing fields)" "$R" "isError" "true"

# ---- 20. MANAGE ACCOUNT - update ----
echo "[20/40] manage_account - update HDFC name..."
R=$(call_tool "manage_account" '{"action":"update","id":1,"notes":"Primary daily account"}')
check "manage_account (update)" "$R" "Account updated"

# ---- 21. LIST ACCOUNTS (3 accounts) ----
echo "[21/40] list_accounts - 3 accounts..."
R=$(call_tool "list_accounts" "{}")
check "list_accounts (3 accounts)" "$R" "HDFC Savings"

# ---- 22. LOG EXPENSE - valid ----
echo "[22/40] log_expense - Swiggy dinner..."
R=$(call_tool "log_expense" '{"account_id":1,"category":"food","amount":450,"expense_date":"2025-02-15","payment_method":"upi","description":"Swiggy dinner"}')
check "log_expense (valid)" "$R" "Expense logged"

# ---- 23. LOG EXPENSE - invalid category ----
echo "[23/40] log_expense - invalid category..."
R=$(call_tool "log_expense" '{"account_id":1,"category":"invalid","amount":100,"payment_method":"upi"}')
check "log_expense (bad category)" "$R" "isError" "true"

# ---- 24. LOG EXPENSE - invalid account ----
echo "[24/40] log_expense - invalid account..."
R=$(call_tool "log_expense" '{"account_id":999,"category":"food","amount":100,"payment_method":"upi"}')
check "log_expense (bad account)" "$R" "isError" "true"

# ---- 25. LOG EXPENSE - second expense ----
echo "[25/40] log_expense - groceries..."
R=$(call_tool "log_expense" '{"account_id":1,"category":"groceries","amount":1200,"expense_date":"2025-02-10","payment_method":"card","description":"BigBasket monthly"}')
check "log_expense (groceries)" "$R" "Expense logged"

# ---- 26. LOG EXPENSE - cash expense ----
echo "[26/40] log_expense - chai cash..."
R=$(call_tool "log_expense" '{"account_id":2,"category":"food","amount":60,"expense_date":"2025-02-12","payment_method":"cash","description":"Chai and samosa"}')
check "log_expense (cash)" "$R" "Expense logged"

# ---- 27. LIST EXPENSES - all ----
echo "[27/40] list_expenses - all..."
R=$(call_tool "list_expenses" "{}")
check "list_expenses (all)" "$R" "\"count\": 3"

# ---- 28. LIST EXPENSES - filter by category ----
echo "[28/40] list_expenses - food only..."
R=$(call_tool "list_expenses" '{"category":"food"}')
check "list_expenses (food)" "$R" "\"count\": 2"

# ---- 29. UPDATE EXPENSE - change amount ----
echo "[29/40] update_expense - correct amount..."
R=$(call_tool "update_expense" '{"id":1,"amount":380,"description":"Swiggy dinner (corrected)"}')
check "update_expense (amount)" "$R" "Expense updated"

# ---- 30. UPDATE EXPENSE - invalid id ----
echo "[30/40] update_expense - bad id..."
R=$(call_tool "update_expense" '{"id":999,"amount":100}')
check "update_expense (bad id)" "$R" "isError" "true"

# ---- 31. LOG INCOME - salary ----
echo "[31/40] log_income - salary..."
R=$(call_tool "log_income" '{"account_id":3,"source":"salary","amount":120000,"income_date":"2025-02-01","description":"Feb salary"}')
check "log_income (salary)" "$R" "Income logged"

# ---- 32. LOG INCOME - freelance ----
echo "[32/40] log_income - freelance..."
R=$(call_tool "log_income" '{"account_id":1,"source":"freelance","amount":15000,"income_date":"2025-02-05","description":"UI project payment"}')
check "log_income (freelance)" "$R" "Income logged"

# ---- 33. LOG INCOME - invalid source ----
echo "[33/40] log_income - bad source..."
R=$(call_tool "log_income" '{"account_id":1,"source":"lottery","amount":100}')
check "log_income (bad source)" "$R" "isError" "true"

# ---- 34. LIST INCOMES ----
echo "[34/40] list_incomes..."
R=$(call_tool "list_incomes" "{}")
check "list_incomes" "$R" "\"count\": 2"

# ---- 35. UPDATE INCOME ----
echo "[35/40] update_income - add bonus..."
R=$(call_tool "update_income" '{"id":1,"amount":125000,"notes":"Included performance bonus"}')
check "update_income" "$R" "Income updated"

# ---- 36. TRANSFER FUNDS ----
echo "[36/40] transfer_funds - SBI to HDFC..."
R=$(call_tool "transfer_funds" '{"from_account_id":3,"to_account_id":1,"amount":50000,"transfer_date":"2025-02-02","notes":"Monthly savings transfer"}')
check "transfer_funds" "$R" "Transfer complete"

# ---- 37. TRANSFER FUNDS - same account ----
echo "[37/40] transfer_funds - same account..."
R=$(call_tool "transfer_funds" '{"from_account_id":1,"to_account_id":1,"amount":100}')
check "transfer_funds (same account)" "$R" "isError" "true"

# ---- 38. LIST TRANSFERS ----
echo "[38/40] list_transfers..."
R=$(call_tool "list_transfers" "{}")
check "list_transfers" "$R" "\"count\": 1"

# ---- 39. REQUEST DELETE ----
echo "[39/40] request_delete - expense..."
R=$(call_tool "request_delete" '{"table_name":"expenses","record_id":3,"reason":"Test deletion"}')
check "request_delete" "$R" "Delete request created"
# Extract delete request id from nested JSON
DR_ID=$(echo "$R" | sed 's/\\n/\n/g' | sed 's/\\"/"/g' | grep -o '"id": [0-9]*' | head -1 | grep -o '[0-9]*')
echo "  -> Delete request ID: $DR_ID"

# ---- 40. CONFIRM DELETE ----
echo "[40/40] confirm_delete..."
R=$(call_tool "confirm_delete" "{\"delete_request_id\":${DR_ID}}")
check "confirm_delete" "$R" "Record deleted successfully"

# ---- BONUS: Validation tests ----
echo "[B1] confirm_delete - already done..."
R=$(call_tool "confirm_delete" "{\"delete_request_id\":${DR_ID}}")
check "confirm_delete (already done)" "$R" "isError" "true"

echo "[B2] confirm_delete - nonexistent..."
R=$(call_tool "confirm_delete" '{"delete_request_id":999}')
check "confirm_delete (not found)" "$R" "isError" "true"

echo "[B3] request_delete - bad table..."
R=$(call_tool "request_delete" '{"table_name":"users","record_id":1}')
check "request_delete (bad table)" "$R" "isError" "true"

echo "[B4] request_delete - bad record..."
R=$(call_tool "request_delete" '{"table_name":"expenses","record_id":9999}')
check "request_delete (bad record)" "$R" "isError" "true"

echo "[B5] manage_preference - add without instruction..."
R=$(call_tool "manage_preference" '{"action":"add","key":"test"}')
check "manage_preference (no instruction)" "$R" "isError" "true"

echo "[B6] manage_preference - invalid action..."
R=$(call_tool "manage_preference" '{"action":"destroy","key":"test"}')
check "manage_preference (bad action)" "$R" "isError" "true"

echo "[B7] manage_account - invalid action..."
R=$(call_tool "manage_account" '{"action":"destroy"}')
check "manage_account (bad action)" "$R" "isError" "true"

echo "[B8] log_expense - zero amount..."
R=$(call_tool "log_expense" '{"account_id":1,"category":"food","amount":0,"payment_method":"upi"}')
check "log_expense (zero amount)" "$R" "isError" "true"

echo "[B9] log_expense - invalid payment method..."
R=$(call_tool "log_expense" '{"account_id":1,"category":"food","amount":100,"payment_method":"bitcoin"}')
check "log_expense (bad payment)" "$R" "isError" "true"

echo "[B10] transfer_funds - bad source account..."
R=$(call_tool "transfer_funds" '{"from_account_id":999,"to_account_id":1,"amount":100}')
check "transfer_funds (bad from)" "$R" "isError" "true"

# ---- RE-ADD deleted data ----
echo ""
echo "Re-adding deleted expense for final DB state..."
call_tool "log_expense" '{"account_id":2,"category":"food","amount":60,"expense_date":"2025-02-12","payment_method":"cash","description":"Chai and samosa"}' > /dev/null

# ---- GET SUMMARY ----
echo "[S1] get_summary - this_month..."
R=$(call_tool "get_summary" '{"period":"this_month"}')
check "get_summary (this_month)" "$R" "total_balance"

echo "[S2] get_summary - this_year..."
R=$(call_tool "get_summary" '{"period":"this_year"}')
check "get_summary (this_year)" "$R" "top_categories"

# ---- FINAL BALANCE CHECK ----
echo ""
echo "[BALANCE] Verifying account balances..."
R=$(call_tool "list_accounts" "{}")
check "list_accounts (final balances)" "$R" "HDFC Savings"

echo ""
echo "============================================"
echo "RESULTS: $PASS passed, $FAIL failed"
echo "============================================"
echo ""
echo -e "$RESULTS"
