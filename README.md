# Pocket Mail to Bear

_Import quotes and comments from Pocket emails into Bear_

## Purpose

For a few years I have used Pocket to read articles from the internet.  When I find a quote of interest, I would share that quote with myself via Pocket's share by email feature.  I ended up with over a thousand emails to myself and no great way to pull them into my editable notes.  I decided to make a weekend project out of the problem and built a script.

## What it does

The project logs into Gmail via IMAP, looks for all Pocket-formatted emails attached to a certain label, and extracts relevant information from the email: article title, article link, your comment, and your quote.  It then creates a bash script to import the above information into Bear.  The script will create a new note in Bear for each article title.  If there are multiple emails per article title, the comments and quotes will be merged into one Bear note.

## How to set it up

```sh
# Clone the repo
git clone https://github.com/lightster/pocket-mail-to-bear.git

# Copy the distribution config file
cp dist.env .env

# Create an app password and save it for the next step
open https://myaccount.google.com/apppasswords

# Configure the script using your Gmail email address,
# the app password you created in the last step, and
# the Gmail label that your Pocket emails are labeled with
vim .env

# Run the Docker container
docker-compose up

# Run the import script
bash results.sh
```
