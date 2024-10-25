# disable xdebug
alias wp="php -d xdebug.mode=off /usr/local/bin/wp"

wp core install --url=http://localhost --title="${WP_TITLE:=Demo}" --admin_user="${WP_USER:=admin}" --admin_email=${WP_EMAIL:="demo@local.tld"} --admin_password="${WP_PASS:=demo}" --skip-email
# Remove unused plugins
wp plugin uninstall hello akismet
# Install necessary plugins
wp plugin install woocommerce --version=9.3.3
# Install neccesary plugin
wp plugin activate woocommerce
wp plugin activate wc-invoice-pdf

# create necessary pages
found=$(wp post list --format=count --title="Legacy Cart" --post_type=page)
[ $found -eq 0 ] && wp post create  --post_type=page --post_title="Legacy Cart" --post_content="[woocommerce_cart]" --post_status="publish"
found=$(wp post list --format=count --title="Legacy Checkout" --post_type=page)
[ $found -eq 0 ] && wp post create  --post_type=page --post_title="Legacy Checkout" --post_content="[woocommerce_checkout]" --post_status="publish"
