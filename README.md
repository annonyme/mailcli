# mailcli (aoop-ng module)

Send email to multiple customers at once. Put your data in a Json-file ("mail" column hs to contain the customers email-address) and use Twig-Templates
for subject and mail-body (set as string, file-path or column reference).

## Examples

### JSON
```
php cli.php mail:multi:send --from="test@example.com" --fromname="aoop" --data="modules/mailcli/deploy/testdata/mails.json" --subject="file:modules/mailcli/deploy/testdata/subject.twig" --body="file:modules/mailcli/deploy/testdata/body.twig"
```

### CSV
```
php cli.php mail:multi:send --from="test@example.com" --fromname="aoop" --data="modules/mailcli/deploy/testdata/mails.csv" --subject="file:modules/mailcli/deploy/testdata/subject.twig" --body="file:modules/mailcli/deploy/testdata/body.twig"
```

## WIP

Customer fullname next to the email-address. CSV-Support.
