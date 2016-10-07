<?php
namespace Server;

/**
 * User: jqh
 * Date: 16-9-15
 * Time: 19:15
 */

class SwooleMarco
{
    /**
     * 包头长度。对websocket无效
     */
    const HEADER_LENGTH = 4;
    /**
     * 获取服务器ID
     */
    const MSG_TYPE_USID = -1;
    /**
     * 发送消息
     */
    const MSG_TYPE_SEND = 0;
    /**
     * 批量发消息
     */
    const MSG_TYPE_SEND_BATCH = 1;
    /**
     * 全服广播
     */
    const MSG_TYPE_SEND_ALL = 2;
    /**
     * 发送给群
     */
    const MSG_TYPE_SEND_GROUP = 3;
    
    /**
     * REDIS 异步回调消息
     */
    const MSG_TYPE_REDIS_MESSAGE = 6000;

    /**
     * 添加server
     */
    const ADD_SERVER = 3003;

    /**
     * task任务
     */
    const SERVER_TYPE_TASK = 500;
    /**
     * 添加dispatch
     */
    const ADD_DISPATCH_CLIENT = 2001;
    /**
     * 移除dispatch
     */
    const REMOVE_DISPATCH_CLIENT = 2002;
    
    /**
     * 任务进程状态hash表key
     * */
    const TASK_WORKER_STATUS_HASH_KEY = 'JQH_TASK_WORKER_STATUS'; 

    /**
     * redis uid和全局usid映射表的hashkey
     * @var string
     */
    const redis_uid_usid_hash_name = '@server_uid_usid';

    /**
     *  redis group前缀
     */
    const redis_group_hash_name_prefix = '@server_group_';
}