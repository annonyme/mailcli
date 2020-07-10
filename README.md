# mailcli (aoop-ng module)

Send email to multiple customers at once. Put your data in a Json-file ("mail" column hs to contain the customers email-address) and use Twig-Templates
for subject and mail-body (set as string, file-path or column reference).

## Using attachments
You can define attachments in the data:

### CSV
```
mail;name;voucher;attachment;_attachment_type
a@example.com;Customer No1;11111;modules/mailcli/deploy/testdata/dummy.txt;attachment
```

### JSON
```
{
    "mail": "b@example.com",
    "name": "Customer No2",
    "voucher": "11112",
    "file": {
        "_type": "attachment",
        "uri": ";modules/mailcli/deploy/testdata/dummy.txt"
    }
}
```

## Examples

### JSON
```
php cli.php mail:multi:send --from="test@example.com" --fromname="aoop" --data="modules/mailcli/deploy/testdata/mails.json" --subject="file:modules/mailcli/deploy/testdata/subject.twig" --body="file:modules/mailcli/deploy/testdata/body.twig"
```

### CSV
```
php cli.php mail:multi:send --from="test@example.com" --fromname="aoop" --data="modules/mailcli/deploy/testdata/mails.csv" --subject="file:modules/mailcli/deploy/testdata/subject.twig" --body="file:modules/mailcli/deploy/testdata/body.twig"
```

### YAML
```
php cli.php mail:multi:send --from="test@example.com" --fromname="aoop" --data="modules/mailcli/deploy/testdata/mails.yml" --subject="file:modules/mailcli/deploy/testdata/subject.twig" --body="file:modules/mailcli/deploy/testdata/body.twig"
```

### with range (from ...zero-indexed: 1+2)
```
php cli.php mail:multi:send --rangefrom=1 --from="test@example.com" --fromname="aoop" --data="modules/mailcli/deploy/testdata/mails.json" --subject="file:modules/mailcli/deploy/testdata/subject.twig" --body="file:modules/mailcli/deploy/testdata/body.twig"
```

### with range (to ...zero-indexed: 0+1)
```
php cli.php mail:multi:send --rangeto=1 --from="test@example.com" --fromname="aoop" --data="modules/mailcli/deploy/testdata/mails.json" --subject="file:modules/mailcli/deploy/testdata/subject.twig" --body="file:modules/mailcli/deploy/testdata/body.twig"
```

### with range (from, to ...zero-indexed: 0+1+2)
```
php cli.php mail:multi:send --rangefrom=0 --rangeto=2 --from="test@example.com" --fromname="aoop" --data="modules/mailcli/deploy/testdata/mails.json" --subject="file:modules/mailcli/deploy/testdata/subject.twig" --body="file:modules/mailcli/deploy/testdata/body.twig"
```

## WIP:

* Customer fullname next to the email-address
* break on first error (as flag)
* transaction-file: write all success-indexes value to a file, on the next run skip this indexes
* SQL-file (the file should include the connection data, using pdbc)
