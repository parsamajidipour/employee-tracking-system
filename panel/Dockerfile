FROM node:22-slim

WORKDIR /app

# Full source before install — `npm install`'s postinstall (`nuxt prepare`)
# needs app/ present to generate a correct .nuxt cache, not a stale one
# built against an empty tree.
COPY . .
RUN npm install

EXPOSE 3000

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "3000"]
