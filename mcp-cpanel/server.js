import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";
import https from "https";

const CPANEL_HOST = process.env.CPANEL_HOST || "zimbocrumbbakery.co.zw";
const CPANEL_PORT = process.env.CPANEL_PORT || "2083";
const CPANEL_USER = process.env.CPANEL_USER || "zimbocrumbbakery";
const CPANEL_TOKEN = process.env.CPANEL_TOKEN || "";

function textResult(payload) {
  return {
    content: [{ type: "text", text: JSON.stringify(payload, null, 2) }],
  };
}

function cpanelRequest(endpoint, params = {}) {
  return new Promise((resolve, reject) => {
    const query = new URLSearchParams(params).toString();
    const path = `${endpoint}${query ? "?" + query : ""}`;

    const options = {
      hostname: CPANEL_HOST,
      port: parseInt(CPANEL_PORT),
      path: path,
      method: "GET",
      headers: {
        Authorization: `cpanel ${CPANEL_USER}:${CPANEL_TOKEN}`,
      },
      rejectUnauthorized: false,
    };

    const req = https.request(options, (res) => {
      let data = "";
      res.on("data", (chunk) => (data += chunk));
      res.on("end", () => {
        try {
          resolve(JSON.parse(data));
        } catch {
          resolve({ raw: data });
        }
      });
    });

    req.on("error", reject);
    req.setTimeout(30000, () => {
      req.destroy();
      reject(new Error("Request timed out"));
    });
    req.end();
  });
}

function cpanelPost(endpoint, params = {}) {
  return new Promise((resolve, reject) => {
    const body = new URLSearchParams(params).toString();

    const options = {
      hostname: CPANEL_HOST,
      port: parseInt(CPANEL_PORT),
      path: endpoint,
      method: "POST",
      headers: {
        Authorization: `cpanel ${CPANEL_USER}:${CPANEL_TOKEN}`,
        "Content-Type": "application/x-www-form-urlencoded",
        "Content-Length": Buffer.byteLength(body),
      },
      rejectUnauthorized: false,
    };

    const req = https.request(options, (res) => {
      let data = "";
      res.on("data", (chunk) => (data += chunk));
      res.on("end", () => {
        try {
          resolve(JSON.parse(data));
        } catch {
          resolve({ raw: data });
        }
      });
    });

    req.on("error", reject);
    req.setTimeout(30000, () => {
      req.destroy();
      reject(new Error("Request timed out"));
    });
    req.write(body);
    req.end();
  });
}

const server = new McpServer({
  name: "bakeflow-cpanel",
  version: "1.0.0",
});

// ─── Account Info ───
server.tool("cpanel_account_info", "Get cPanel account information", {}, async () => {
  const result = await cpanelRequest(`/execute/Variables/get_user_information`);
  return textResult(result);
});

// ─── List Files ───
server.tool(
  "cpanel_list_files",
  "List files in a directory on the server",
  {
    dir: z
      .string()
      .default("/home/zimbocrumbbakery")
      .describe("Directory path to list"),
  },
  async ({ dir }) => {
    const result = await cpanelRequest(`/execute/Fileman/list_files`, {
      dir: dir,
      include_mime: 1,
      include_permissions: 1,
    });
    return textResult(result);
  }
);

// ─── Create Directory ───
server.tool(
  "cpanel_create_directory",
  "Create a directory on the server",
  {
    path: z.string().describe("Full path for the new directory"),
    name: z.string().describe("Name of the directory to create"),
  },
  async ({ path, name }) => {
    const result = await cpanelRequest(`/execute/Fileman/mkdir`, {
      path: path,
      name: name,
    });
    return textResult(result);
  }
);

// ─── Write File ───
server.tool(
  "cpanel_write_file",
  "Write content to a file on the server",
  {
    dir: z.string().describe("Directory path"),
    filename: z.string().describe("File name"),
    content: z.string().describe("File content"),
  },
  async ({ dir, filename, content }) => {
    const result = await cpanelPost(`/execute/Fileman/save_file_content`, {
      dir: dir,
      file: filename,
      content: content,
    });
    return textResult(result);
  }
);

// ─── Read File ───
server.tool(
  "cpanel_read_file",
  "Read a file from the server",
  {
    dir: z.string().describe("Directory path"),
    file: z.string().describe("File name"),
  },
  async ({ dir, file }) => {
    const result = await cpanelRequest(`/execute/Fileman/get_file_content`, {
      dir: dir,
      file: file,
    });
    return textResult(result);
  }
);

// ─── List Domains ───
server.tool("cpanel_list_domains", "List all domains on this account", {}, async () => {
  const result = await cpanelRequest(`/execute/DomainInfo/list_domains`);
  return textResult(result);
});

// ─── Domain Info ───
server.tool(
  "cpanel_domain_info",
  "Get details about a specific domain",
  {
    domain: z.string().describe("Domain name to query"),
  },
  async ({ domain }) => {
    const result = await cpanelRequest(`/execute/DomainInfo/single_domain_data`, {
      domain: domain,
    });
    return textResult(result);
  }
);

// ─── SSL Status ───
server.tool("cpanel_ssl_status", "Check SSL certificate status", {}, async () => {
  const result = await cpanelRequest(`/execute/SSL/list_certs`);
  return textResult(result);
});

// ─── Disk Usage ───
server.tool("cpanel_disk_usage", "Get disk usage information", {}, async () => {
  const result = await cpanelRequest(`/execute/Quota/get_local_quota_info`);
  return textResult(result);
});

// ─── Error Log ───
server.tool(
  "cpanel_error_log",
  "Get recent entries from the error log",
  {},
  async () => {
    const result = await cpanelRequest(`/execute/Logd/get_recent_errors`);
    return textResult(result);
  }
);

// ─── PHP Version ───
server.tool("cpanel_php_version", "Get the current PHP version", {}, async () => {
  const result = await cpanelRequest(`/execute/LangPHP/php_get_vhost_versions`);
  return textResult(result);
});

const transport = new StdioServerTransport();
await server.connect(transport);
